@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-md-6 breadcrumb-box"></div>
    <div class="col-md-6 mb-2 text-right">
        <h4 class="card-title d-none">{{ _lang('Mood Suggestions') }}</h4>
        <a class="btn btn-primary btn-sm ajax-modal" href="{{ route('mood_suggestions.create') }}" data-title="{{ _lang('Add Mood Suggestion') }}">
            <i class="fas fa-plus mr-1"></i>{{ _lang('Add New') }}
        </a>
    </div>
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <table class="table table-bordered" id="data-table">
                    <thead>
                        <tr>
                            <th>{{ _lang('Text') }}</th>
                            <th>{{ _lang('Sort Order') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th class="text-center">{{ _lang('Action') }}</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js-script')
<script src="{{ asset('public/backend/js/pages/mood_suggestions.js') }}"></script>
@endsection
