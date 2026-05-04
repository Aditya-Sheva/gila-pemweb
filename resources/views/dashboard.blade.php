@extends('layouts.app')
@section('title','Dashboard')
@section('page-title','Dashboard')

@section('content')
@php
$statusColors=['pending'=>'secondary','document_check'=>'info','under_review'=>'primary','approved'=>'success','approved_with_recommendation'=>'warning','resubmission'=>'warning','disapproved'=>'danger','data_confirmation'=>'warning','waiting_signature'=>'primary','published'=>'success'];
$statusLabels=['pending'=>'Pending','document_check'=>'Cek Dokumen','under_review'=>'Direview','approved'=>'Disetujui','approved_with_recommendation'=>'Disetujui+Rek','resubmission'=>'Revisi','disapproved'=>'Ditolak','data_confirmation'=>'Konfirmasi Data','waiting_signature'=>'Menunggu TTD','published'=>'Published'];
@endphp

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
@if(auth()->user()->isSecretary() || auth()->user()->isAdmin())
  <div class="col-6 col-xl-3 fade-up"><div class="stat-card bg-blue"><i class="bi bi-folder2 stat-icon"></i><div class="stat-number">{{ $data['total'] }}</div><div class="stat-label">Total Proposal</div></div></div>
  <div class="col-6 col-xl-3 fade-up delay-1"><div class="stat-card bg-orange"><i class="bi bi-hourglass-split stat-icon"></i><div class="stat-number">{{ $data['pending'] }}</div><div class="stat-label">Menunggu Verifikasi</div></div></div>
  <div class="col-6 col-xl-3 fade-up delay-2"><div class="stat-card bg-teal"><i class="bi bi-eye stat-icon"></i><div class="stat-number">{{ $data['under_review'] }}</div><div class="stat-label">Dalam Review</div></div></div>
  <div class="col-6 col-xl-3 fade-up delay-3"><div class="stat-card bg-green"><i class="bi bi-check-circle stat-icon"></i><div class="stat-number">{{ $data['approved'] }}</div><div class="stat-label">Disetujui</div></div></div>

@elseif(auth()->user()->isReviewer())
  <div class="col-6 col-xl-4 fade-up"><div class="stat-card bg-blue"><i class="bi bi-clipboard2 stat-icon"></i><div class="stat-number">{{ $data['assigned'] }}</div><div class="stat-label">Total Ditugaskan</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-1"><div class="stat-card bg-orange"><i class="bi bi-hourglass stat-icon"></i><div class="stat-number">{{ $data['pending'] }}</div><div class="stat-label">Belum Direview</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-2"><div class="stat-card bg-green"><i class="bi bi-check2-all stat-icon"></i><div class="stat-number">{{ $data['completed'] }}</div><div class="stat-label">Selesai</div></div></div>

@elseif(auth()->user()->isKetua())
  <div class="col-6 col-xl-4 fade-up"><div class="stat-card bg-blue"><i class="bi bi-pen stat-icon"></i><div class="stat-number">{{ $data['waiting_signature'] }}</div><div class="stat-label">Menunggu Tanda Tangan</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-1"><div class="stat-card bg-green"><i class="bi bi-check2-circle stat-icon"></i><div class="stat-number">{{ $data['signed'] }}</div><div class="stat-label">Sudah Ditandatangani</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-2"><div class="stat-card bg-teal"><i class="bi bi-award stat-icon"></i><div class="stat-number">{{ $data['published'] }}</div><div class="stat-label">Published</div></div></div>

@else
  <div class="col-6 col-xl-4 fade-up"><div class="stat-card bg-blue"><i class="bi bi-folder2 stat-icon"></i><div class="stat-number">{{ $data['total'] }}</div><div class="stat-label">Total Proposal</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-1"><div class="stat-card bg-orange"><i class="bi bi-hourglass-split stat-icon"></i><div class="stat-number">{{ $data['pending'] }}</div><div class="stat-label">Sedang Diproses</div></div></div>
  <div class="col-6 col-xl-4 fade-up delay-2"><div class="stat-card bg-green"><i class="bi bi-award stat-icon"></i><div class="stat-number">{{ $data['approved'] }}</div><div class="stat-label">Published</div></div></div>
@endif
</div>

<!-- CTA Peneliti -->
@if(auth()->user()->isPeneliti())
<div class="card mb-4 fade-up delay-2" style="background:linear-gradient(90deg,#1e3a5f,#2563eb);border:none">
  <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <h6 class="text-white fw-bold mb-1">Ajukan Proposal Baru</h6>
      <p class="text-white mb-0" style="opacity:.75;font-size:.83rem">Upload proposal penelitian Anda untuk mendapatkan ethical clearance</p>
    </div>
    <a href="{{ route('proposals.create') }}" class="btn btn-light fw-500" style="border-radius:10px">
      <i class="bi bi-plus-circle me-2"></i>Ajukan Sekarang
    </a>
  </div>
</div>
@endif

<!-- TABLE -->
<div class="card fade-up delay-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h6 class="card-header-title"><i class="bi bi-clock-history me-2" style="color:#2563eb"></i>Proposal Terbaru</h6>
    <a href="{{ route('proposals.index') }}" class="btn btn-outline-primary btn-sm">Lihat Semua</a>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th class="ps-4">Judul Proposal</th>
          <th>Peneliti</th>
          <th>Tanggal</th>
          <th>Status</th>
          <th class="pe-4">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($data['proposals'] as $p)
        <tr>
          <td class="ps-4">
            <div style="font-weight:500;font-size:.875rem;color:#1e293b">{{ Str::limit($p->title,50) }}</div>
          </td>
          <td><span style="font-size:.82rem;color:#64748b">{{ $p->researcher_name }}</span></td>
          <td><span style="font-size:.82rem;color:#64748b">{{ \Carbon\Carbon::parse($p->submission_date)->format('d M Y') }}</span></td>
          <td>
            <span class="badge-status status-{{ $p->status }}">
              {{ $statusLabels[$p->status] ?? $p->status }}
            </span>
          </td>
          <td class="pe-4">
            <a href="{{ route('proposals.show',$p) }}" class="btn btn-sm btn-outline-primary" style="border-radius:8px">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
        @empty
        <tr><td colspan="5">
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h6 class="mb-1">Belum ada proposal</h6>
            <p class="text-muted small mb-0">Proposal yang diajukan akan muncul di sini</p>
          </div>
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection