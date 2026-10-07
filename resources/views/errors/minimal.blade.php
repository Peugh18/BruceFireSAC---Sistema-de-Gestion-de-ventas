@extends('errors.layout')

@section('title', $message ?? 'Ha ocurrido un error')
@section('code', $exception->getStatusCode() ?? 'Error')
@section('chispa_pose', 'sentado')
@section('message', 'Si el problema persiste, por favor contacta al administrador del sistema.')

@section('actions')
    <a href="javascript:history.back()" class="btn-secondary">
        ← Volver atrás
    </a>
@endsection
