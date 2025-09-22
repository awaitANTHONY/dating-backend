@extends('layouts.app')
@section('content')
<div class="card-title" style="display: none;">{{ _lang('General Settings') }}</div>
<div class="row">
    <div class="col-md-3">
        <div class="nav flex-column nav-pills nav-primary nav-pills-no-bd" id="v-pills-tab-without-border" role="tablist" aria-orientation="vertical">
            <a class="nav-link active show" id="v-pills-general-settings-tab" data-toggle="pill" href="#v-pills-general-settings" role="tab" aria-controls="v-pills-general-settings" aria-selected="true">{{ _lang('General Settings') }}</a>
            <a class="nav-link" id="v-pills-api-tab" data-toggle="pill" href="#v-pills-api" role="tab" aria-controls="v-pills-api" aria-selected="false">{{ _lang('Api Settings') }}</a>
            <a class="nav-link" id="v-pills-Email-tab" data-toggle="pill" href="#v-pills-Email" role="tab" aria-controls="v-pills-Email" aria-selected="false">{{ _lang('Email Configuration') }}</a>
            <a class="nav-link" id="v-pills-Firebase-tab" data-toggle="pill" href="#v-pills-Firebase" role="tab" aria-controls="v-pills-Firebase" aria-selected="false">{{ _lang('Firebase Configuration') }}</a>
            <a class="nav-link" id="v-pills-logo-tab" data-toggle="pill" href="#v-pills-logo" role="tab" aria-controls="v-pills-logo" aria-selected="false">{{ _lang('Logo & Icon') }}</a>
        </div>
    </div>
    <div class="col-md-9">
        <div class="tab-content" id="v-pills-without-border-tabContent">
            <div class="tab-pane fade active show" id="v-pills-general-settings" role="tabpanel" aria-labelledby="v-pills-general-settings-tab">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('General Settings') }}</h3>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('App Name') }}</label>
                                        <input type="text" class="form-control" name="app_name" value="{{ get_option('app_name') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Site Title') }}</label>
                                        <input type="text" class="form-control" name="site_title" value="{{ get_option('site_title') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Timezone') }}</label>
                                        <select class="form-control select2" name="timezone" required>
                                            <option value="">{{ _lang('Select One') }}</option>
                                            {{ create_timezone_option(get_option('timezone')) }}
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Language') }}</label>
                                        <select class="form-control select2" name="language" required>
                                            {{ load_language( get_option('language') ) }}
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ _lang('Update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="v-pills-api" role="tabpanel" aria-labelledby="v-pills-api-tab">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Api Settings') }}</h3>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('X-RapidAPI-Key') }}</label>
                                        <input type="text" class="form-control" name="x_rapidapi_key" value="{{ get_option('x_rapidapi_key') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('X-RapidAPI-Host') }}</label>
                                        <input type="text" class="form-control" name="x_rapidapi_host" value="{{ get_option('x_rapidapi_host') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ _lang('Update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="v-pills-Email" role="tabpanel" aria-labelledby="v-pills-Email-tab">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="mb-3 header-title card-title">{{ _lang('Email Configuration') }}</h3>
                            <form method="post" class="ajax-submit2 params-card" autocomplete="off"
                                action="{{ route('store_settings') }}">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('From Mail') }}</label>
                                            <input type="email" class="form-control" name="from_mail"
                                                value="{{ get_option('from_mail') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('From Name') }}</label>
                                            <input type="text" class="form-control" name="from_name"
                                                value="{{ get_option('from_name') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('SMTP Host') }}</label>
                                            <input type="text" class="form-control smtp" name="smtp_host"
                                                value="{{ get_option('smtp_host') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('SMTP Port') }}</label>
                                            <input type="text" class="form-control smtp" name="smtp_port"
                                                value="{{ get_option('smtp_port') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('SMTP Username') }}</label>
                                            <input type="email" class="form-control smtp" autocomplete="off"
                                                name="smtp_username" value="{{ get_option('smtp_username') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('SMTP Password') }}</label>
                                            <input type="password" class="form-control smtp" autocomplete="off"
                                                name="smtp_password" value="{{ get_option('smtp_password') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="control-label">{{ _lang('SMTP Encryption') }}</label>
                                            <select class="form-control smtp" name="smtp_encryption"
                                                data-selected="{{ get_option('smtp_encryption') }}" required>
                                                <option value="ssl">{{ _lang('SSL') }}</option>
                                                <option value="tls">{{ _lang('TLS') }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group text-right">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                {{ _lang('Update') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
<div class="tab-pane fade" id="v-pills-Firebase" role="tabpanel" aria-labelledby="v-pills-Firebase-tab">
            
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Firebase Configuration') }}</h3>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Firebase Json') }} <span class="text-danger">(Service Account)</span></label>
                                        <input type="file" class="form-control dropify" name="firebase_json" data-allowed-file-extensions="json" data-default-file="{{ public_path('uploads/files/' . get_option('firebase_json')) }}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-control-label">Firebase Project Id</label>
                                        <input type="text" name="firebase_project_id" class="form-control" value="{{ get_option('firebase_project_id', 'N/A') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-control-label">Firebase Database URL</label>
                                        <input type="text" name="firebase_database_url" class="form-control" value="{{ get_option('firebase_database_url', 'N/A') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-control-label">Firebase Topics</label>
                                        <input type="text" name="firebase_topics" class="form-control" value="{{ get_option('firebase_topics', 'N/A') }}" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ _lang('Update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="v-pills-logo" role="tabpanel" aria-labelledby="v-pills-logo-tab">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-3 header-title card-title">{{ _lang('Logo & Icon') }}</h3>
                        <form method="post" class="ajax-submit2 params-card" autocomplete="off" action="{{ route('store_settings') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Logo') }} <span class="text-danger">(325w X 89h)</span></label>
                                        <input type="file" class="form-control dropify" name="logo" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-default-file="{{ get_logo() }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Site Icon') }} <span class="text-danger">(100w X 100h)</label>
                                        <input type="file" class="form-control dropify" name="icon" data-allowed-file-extensions="png PNG" data-default-file="{{ get_icon() }}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group text-right">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            {{ _lang('Update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script type="text/javascript">
    $(document).on('click', '.add-more', function(){
        var form = $('.repeat').clone().removeClass('repeat');

        $(this).closest('.col-md-12').before(form);
    });
    $(document).on('click','.remove-row',function(){
        $(this).closest('.col-md-12').remove();
    });
</script>
@endsection