@extends('errors.layout')

@section('title', __('errors.500_title'))
@section('code', '500')
@section('heading', __('errors.500_heading'))
@section('body', __('errors.500_body'))
@section('icon', 'warning')
@section('icon-bg', 'bg-tertiary-container dark:bg-[#3f2e00]')
@section('icon-color', 'text-tertiary dark:text-tertiary-container')
