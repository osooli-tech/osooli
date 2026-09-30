@extends('portal.layout')

@section('title', __('portal.documents_title'))

@section('content')
<div class="space-y-5">
    <x-portal.page-hero icon="folder_open" :title="__('portal.documents_title')" :subtitle="__('portal.documents_hero_subtitle')" id="docs" />

    <livewire:portal.document-index />
</div>
@endsection
