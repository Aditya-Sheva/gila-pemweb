<?php
namespace App\Http\Controllers;

use App\Models\Decision;
use App\Models\Document;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function pendingUsers()
    {
        $users = User::where('role', 'peneliti')
            ->where('is_active', false)
            ->latest()
            ->paginate(10);

        return view('admin.pending-users', compact('users'));
    }

    public function activateUser(User $user)
    {
        // Extra guard: admin hanya boleh mengaktifkan akun peneliti.
        if ($user->role !== 'peneliti') {
            return back()->with('error', 'Hanya akun peneliti yang dapat diaktifkan melalui menu ini.');
        }

        $user->update(['is_active' => true]);

        Mail::raw(
            "Halo {$user->name}, akun Anda sudah diaktifkan. Silakan login ke sistem.",
            fn($m) => $m->to($user->email)->subject('Akun Anda Sudah Aktif')
        );

        return back()->with('success', "Akun {$user->name} berhasil diaktifkan.");
    }

    private function incomingStatuses(): array
    {
        return [
            'pending',
            'document_check',
            'under_review',
            'approved',
            'approved_with_recommendation',
            'resubmission',
            'disapproved',
            'data_confirmation',
            'waiting_signature',
        ];
    }

    public function incomingIndex(Request $request)
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $statuses = $this->incomingStatuses();

        $proposals = Proposal::with(['user', 'documents'])
            ->where('status', '!=', 'published')
            ->when(
                $status !== '' && in_array($status, $statuses, true),
                fn($query) => $query->where('status', $status)
            )
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('researcher_name', 'like', "%{$q}%")
                        ->orWhere('institution', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.incoming.index', compact('proposals', 'statuses', 'status', 'q'));
    }

    public function incomingCreate()
    {
        $researchers = User::where('role', 'peneliti')->where('is_active', true)->orderBy('name')->get();
        $statuses = $this->incomingStatuses();

        return view('admin.incoming.create', compact('researchers', 'statuses'));
    }

    public function incomingStore(Request $request)
    {
        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'researcher_name' => 'required|string|max:255',
            'institution'     => 'nullable|string|max:255',
            'submission_date' => 'required|date',
            'review_type'     => 'nullable|in:exempted,expedited,full_board',
            'status'          => ['required', Rule::in($this->incomingStatuses())],
            'proposal_file'   => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $path = $request->file('proposal_file')->store('proposals', 'public');
        $originalName = $request->file('proposal_file')->getClientOriginalName();
        $mimeType = $request->file('proposal_file')->getMimeType();

        DB::transaction(function () use ($validated, $path, $originalName, $mimeType) {
            $proposal = Proposal::create([
                'user_id'         => $validated['user_id'],
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'researcher_name' => $validated['researcher_name'],
                'institution'     => $validated['institution'] ?? null,
                'submission_date' => $validated['submission_date'],
                'review_type'     => $validated['review_type'] ?? null,
                'status'          => $validated['status'],
                'proposal_file'   => $path,
            ]);

            Document::create([
                'proposal_id'   => $proposal->id,
                'document_name' => $originalName,
                'file_path'     => $path,
                'file_type'     => $mimeType,
                'document_type' => 'proposal',
            ]);
        });

        return redirect()->route('admin.incoming.index')->with('success', 'Proposal masuk berhasil ditambahkan.');
    }

    public function incomingEdit(Proposal $proposal)
    {
        if ($proposal->status === 'published') {
            return redirect()->route('admin.incoming.index')->with('error', 'Proposal published tidak dapat diubah dari menu proposal masuk.');
        }

        $researchers = User::where('role', 'peneliti')->where('is_active', true)->orderBy('name')->get();
        $statuses = $this->incomingStatuses();

        return view('admin.incoming.edit', compact('proposal', 'researchers', 'statuses'));
    }

    public function incomingUpdate(Request $request, Proposal $proposal)
    {
        if ($proposal->status === 'published') {
            return redirect()->route('admin.incoming.index')->with('error', 'Proposal published tidak dapat diubah dari menu proposal masuk.');
        }

        $validated = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'researcher_name' => 'required|string|max:255',
            'institution'     => 'nullable|string|max:255',
            'submission_date' => 'required|date',
            'review_type'     => 'nullable|in:exempted,expedited,full_board',
            'status'          => ['required', Rule::in($this->incomingStatuses())],
            'proposal_file'   => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $newFilePath = null;

        DB::transaction(function () use ($request, $proposal, $validated, &$newFilePath) {
            $payload = [
                'user_id'         => $validated['user_id'],
                'title'           => $validated['title'],
                'description'     => $validated['description'] ?? null,
                'researcher_name' => $validated['researcher_name'],
                'institution'     => $validated['institution'] ?? null,
                'submission_date' => $validated['submission_date'],
                'review_type'     => $validated['review_type'] ?? null,
                'status'          => $validated['status'],
            ];

            if ($request->hasFile('proposal_file')) {
                $newFilePath = $request->file('proposal_file')->store('proposals', 'public');
                $payload['proposal_file'] = $newFilePath;
            }

            $proposal->update($payload);

            if ($newFilePath) {
                Document::create([
                    'proposal_id'   => $proposal->id,
                    'document_name' => $request->file('proposal_file')->getClientOriginalName(),
                    'file_path'     => $newFilePath,
                    'file_type'     => $request->file('proposal_file')->getMimeType(),
                    'document_type' => 'proposal',
                ]);
            }
        });

        return redirect()->route('admin.incoming.index')->with('success', 'Proposal masuk berhasil diperbarui.');
    }

    public function incomingDestroy(Proposal $proposal)
    {
        if ($proposal->status === 'published') {
            return back()->with('error', 'Proposal published tidak dapat dihapus dari menu proposal masuk.');
        }

        $filePaths = collect([$proposal->proposal_file])
            ->merge($proposal->documents()->pluck('file_path'))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($proposal) {
            Review::where('proposal_id', $proposal->id)->delete();
            Decision::where('proposal_id', $proposal->id)->delete();
            Document::where('proposal_id', $proposal->id)->delete();
            $proposal->delete();
        });

        foreach ($filePaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        return back()->with('success', 'Proposal masuk berhasil dihapus.');
    }

    public function templates()
    {
        $templates = Template::with('uploader')->latest()->paginate(10);
        return view('admin.templates', compact('templates'));
    }

    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $path = $request->file('file')->store('templates', 'public');
        Template::create([
            'name'        => $request->name,
            'file_path'   => $path,
            'file_type'   => $request->file('file')->getMimeType(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Template berhasil diupload.');
    }

    public function assignSecretary(Request $request, Proposal $proposal)
    {
        $request->validate([
            'secretary_id' => 'required|exists:users,id',
        ]);

        $proposal->update([
            'assigned_secretary_id' => $request->secretary_id,
        ]);

        return back()->with('success', 'Sekretariat berhasil ditugaskan.');
    }

    public function monitoring()
    {
        $proposals = Proposal::with(['user','documents','decision.chief'])
            ->latest()->paginate(10);
        return view('admin.monitoring', compact('proposals'));
    }

    public function ethicsQueue()
    {
        $proposals = Proposal::with(['user','decision'])
            ->whereIn('status', ['approved', 'approved_with_recommendation'])
            ->latest()->paginate(10);
        $chiefs = User::where('role','ketua')->where('is_active', true)->get();

        return view('admin.ethics', compact('proposals', 'chiefs'));
    }

    public function sendForConfirmation(Request $request, Proposal $proposal)
    {
        if (!in_array($proposal->status, ['approved', 'approved_with_recommendation'], true)) {
            return back()->with('error', 'Proposal belum berada pada tahap keputusan yang dapat dikirim konfirmasi.');
        }

        $request->validate([
            'certificate_number' => 'required|string|max:100',
            'chief_id'           => 'required|exists:users,id',
        ]);

        Decision::updateOrCreate(
            ['proposal_id' => $proposal->id],
            [
                'certificate_number' => $request->certificate_number,
                'chief_id'           => $request->chief_id,
            ]
        );

        $proposal->update(['status' => 'data_confirmation']);

        return back()->with('success', 'Data dikirim ke peneliti untuk konfirmasi.');
    }

    public function publish(Proposal $proposal)
    {
        if ($proposal->status !== 'waiting_signature') {
            return back()->with('error', 'Proposal belum siap dipublish.');
        }

        if (!$proposal->decision || !$proposal->decision->signature_path) {
            return back()->with('error', 'Tanda tangan ketua belum diupload.');
        }

        $proposal->decision()->update(['published_at' => now()]);
        $proposal->update(['status' => 'published']);

        return back()->with('success', 'Surat etik berhasil dipublish.');
    }
}