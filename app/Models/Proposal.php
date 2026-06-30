<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $fillable = [
        'user_id','assigned_secretary_id','title','description','researcher_name',
        'institution','submission_date','review_type','status','proposal_file',
        'is_active','data_confirmed_at'
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function secretary() { return $this->belongsTo(User::class, 'assigned_secretary_id'); }
    public function documents() { return $this->hasMany(Document::class); }
    public function reviews()   { return $this->hasMany(Review::class); }
    public function decision()  { return $this->hasOne(Decision::class); }

    public function getStatusLabelAttribute(): string {
        return match($this->status) {
            'pending'                      => 'Menunggu Verifikasi',
            'document_check'               => 'Pemeriksaan Berkas',
            'under_review'                 => 'Dalam Review',
            'approved'                     => 'Disetujui',
            'approved_with_recommendation' => 'Disetujui + Rekomendasi',
            'resubmission'                 => 'Perlu Revisi',
            'disapproved'                  => 'Ditolak',
            'data_confirmation'            => 'Konfirmasi Data',
            'waiting_signature'            => 'Menunggu Tanda Tangan',
            'published'                    => 'Sudah Dipublikasikan',
            default                        => '-',
        };
    }

    public function getStatusColorAttribute(): string {
        return match($this->status) {
            'approved'                     => 'success',
            'approved_with_recommendation' => 'warning',
            'disapproved'                  => 'danger',
            'resubmission'                 => 'info',
            'under_review'                 => 'primary',
            'document_check'               => 'info',
            'data_confirmation'            => 'warning',
            'waiting_signature'            => 'primary',
            'published'                    => 'success',
            default                        => 'secondary',
        };
    }
}