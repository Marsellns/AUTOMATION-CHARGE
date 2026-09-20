@extends('layouts.app')
@section('title', 'Detail BAPSS — SIMASTER')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
 <h1 class="h4 mb-0">Detail BAPSS — {{ $bapss->site_code }}</h1>
 <div class="d-flex gap-2"><a href="{{ route('infrastruktur.bapss.index') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>@role('admin')<a href="{{ route('infrastruktur.bapss.edit', $bapss) }}" class="btn btn-brand btn-sm">Edit</a>@endrole</div>
</div>
<div class="card"><div class="card-body">
 <dl class="row mb-0">
  @foreach(['site_code'=>'Site ID','site_name'=>'Site Name','tgl_bapss'=>'Tgl BAPSS','tgl_dismantle'=>'Tgl Dismantle','remark'=>'Remark','update_by'=>'Update By','tgl_update'=>'Tgl Update'] as $field=>$label)
   <dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $bapss->{$field} ?? '-' }}</dd>
  @endforeach
  <dt class="col-sm-3">PDF BAPSS</dt><dd class="col-sm-9">@if($bapss->pdf_bapss)<a target="_blank" href="{{ asset('storage/'.$bapss->pdf_bapss) }}">Buka PDF</a>@else - @endif</dd>
  <dt class="col-sm-3">PDF BA Dismantle</dt><dd class="col-sm-9">@if($bapss->pdf_ba_dismantle)<a target="_blank" href="{{ asset('storage/'.$bapss->pdf_ba_dismantle) }}">Buka PDF</a>@else - @endif</dd>
 </dl>
</div></div>
@endsection
