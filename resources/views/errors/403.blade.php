@extends('errors.layout')

@section('title', __('errors.403_title'))
@section('code', '403')
@section('heading', __('errors.403_heading'))
@section('body', __('errors.403_body'))
@section('icon', 'lock')
@section('icon-bg', 'bg-error-container dark:bg-[#93000a]')
@section('icon-color', 'text-on-error-container dark:text-error-container')
