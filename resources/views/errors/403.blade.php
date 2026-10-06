@extends('errors.layout')

@section('title', 'Esta sección no es de tu rol')
@section('code', '403')
@section('chispa_pose', 'piensa')
@section('message', 'Tu cuenta no tiene acceso a esta página o a los datos de esta sede. Si la necesitas para tu trabajo, pídesela a tu gerente.')

@section('actions')
    <a href="javascript:history.back()" class="btn-secondary">
        ← Volver atrás
    </a>
@endsection
