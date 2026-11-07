@extends('layouts.admin')
@section('title', 'Ficha del cliente')
@section('page-title', 'Ficha del cliente')
@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('admin.customers.index') }}" class="inline-block pb-5 underline">← Volver a clientes</a>
    @include('admin.customers.details')
</div>
@endsection
