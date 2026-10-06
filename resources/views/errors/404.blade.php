@extends('errors.layout')

@section('title', 'Chispa buscó esta página y no la encontró')
@section('code', '404')
@section('chispa_pose', 'busca')
@section('message', 'Puede que el enlace esté mal escrito o que la página ya no exista. Revisa la dirección o vuelve a tu panel.')

@section('actions')
    <a href="javascript:history.back()" class="btn-secondary">
        ← Volver atrás
    </a>
@endsection
