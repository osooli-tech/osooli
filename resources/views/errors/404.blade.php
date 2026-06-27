@extends('errors.layout')

@section('title', __('errors.404_title'))
@section('code', '404')
@section('heading', __('errors.404_heading'))
@section('body', __('errors.404_body'))
@section('icon', 'search_off')
@section('icon-bg', 'bg-surface-container dark:bg-primary-container')
@section('icon-color', 'text-on-surface-variant dark:text-primary-fixed-dim')
