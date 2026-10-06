@extends('errors.layout')

@section('title', 'Algo falló de nuestro lado')
@section('code', '500')
@section('chispa_pose', 'corre')
@section('message', 'El error ya quedó registrado. Intenta de nuevo en unos minutos; si se repite, avísale a tu gerente.')

@section('actions')
    <a href="javascript:location.reload()" class="btn-secondary">
        ↻ Reintentar
    </a>
@endsection
