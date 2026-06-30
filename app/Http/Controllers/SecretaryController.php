<?php
namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Models\Review;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SecretaryController extends Controller
{
    public function verifyProposal(Request $request, Proposal $proposal)
    {
        $request->validate(['review_type' => 'required|in:exempted,expedited,full_board']);
        $proposal->update(['review_type' => $request->review_type, 'status' => 'document_check']);
        return back()->with('success', 'Jenis review berhasil ditentukan.');
    }

    public function assignReviewer(Request $request, Proposal $proposal)
    {
        $request->validate(['reviewer_ids' => 'required|array', 'reviewer_ids.*' => 'exists:users,id']);
        foreach ($request->reviewer_ids as $id) {
            Review::firstOrCreate(['proposal_id' => $proposal->id, 'reviewer_id' => $id]);
            $reviewer = User::find($id);
            if ($reviewer) {
                Mail::raw(
                    "Anda ditugaskan untuk mereview proposal: {$proposal->title}.",
                    fn($m) => $m->to($reviewer->email)->subject('Penugasan Review Proposal')
                );
            }
        }
        $proposal->update(['status' => 'under_review']);
        return back()->with('success', 'Reviewer berhasil ditugaskan.');
    }

    public function makeDecision(Request $request, Proposal $proposal)
    {
        $request->validate([
            'decision' => 'required|in:approved,approved_with_recommendation,resubmission,disapproved',
            'notes'    => 'nullable|string',
        ]);

        $certNumber = $request->decision === 'approved'
            ? 'SKE-'.date('Y').'-'.str_pad($proposal->id, 4, '0', STR_PAD_LEFT) : null;

        Decision::updateOrCreate(
            ['proposal_id' => $proposal->id],
            [
                'secretary_id'       => Auth::id(),
                'decision'           => $request->decision,
                'notes'              => $request->notes,
                'decided_at'         => now(),
                'certificate_number' => $certNumber,
            ]
        );

        $newStatus = $request->decision === 'approved' ? 'approved' : $request->decision;
        $proposal->update(['status' => $newStatus]);
        $statusLabel = match($request->decision) {
            'approved' => 'Disetujui',
            'approved_with_recommendation' => 'Disetujui dengan Rekomendasi',
            'resubmission' => 'Perlu Revisi',
            'disapproved' => 'Ditolak',
        };
        $notes = $request->notes ? "\nCatatan: {$request->notes}" : '';
        Mail::raw(
            "Proposal '{$proposal->title}' mendapatkan keputusan: {$statusLabel}.{$notes}",
            fn($m) => $m->to($proposal->user->email)->subject('Keputusan Proposal Ethical Clearance')
        );
        return back()->with('success', 'Keputusan berhasil disimpan.');
    }
}