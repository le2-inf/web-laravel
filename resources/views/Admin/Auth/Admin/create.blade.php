@extends('layouts.app')
@push('css')
    <style>
        .select2-selection__arrow {
            border-color: #888 transparent transparent transparent;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border: 1px solid #ced4da
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            color: #333;
            background-color: white;
        }
    </style>
@endpush
@section('content')

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.admins')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            @can('admin.index')
                                <a href="{{ route('admins.index') }}">@lang('class.admins')@lang('app.methods.list')</a>
                            @endcan
                        </li>
                        <li class="breadcrumb-item active">@lang('app.methods.create')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>


    <section class="content">
        <div class="row">
            <div class="col-sm-12">

                <form action="{{ route('admins.store') }}" method="post">
                    @csrf

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">@lang('class.admins')@lang('app.methods.create')</h3>
                        </div>

                        <div class="card-body">

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.name')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? "is-invalid":"" }}" value="{{ old('name') }}">
                                    @if($errors->has('name'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('name') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.email')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="email" name="email" class="form-control {{ $errors->has('email') ? "is-invalid":"" }}" value="{{ old('email') }}">
                                    @if($errors->has('email'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('email') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label">@lang('class.roles')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <select class="j a_select2" style="width: 100%;" multiple="multiple" name="roles[]" data-placeholder="@lang('app.methods.select_option_please')">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}" @selected(in_array( $role->name, (array)old('roles')))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>

                                    @if($errors->has('roles'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('roles') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.password')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="password" name="password" id="password-field" class="form-control {{ $errors->has('password') ? "is-invalid":"" }}">
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i data-toggle="#password-field" class="fa fa-fw fa-eye j a_toggle_password " role="button"></i>
                                        </span>
                                    </div>
                                    @if($errors->has('password'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('password') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label
                                    class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.password_confirmation')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input id="password_confirmation" type="password" class="form-control {{ $errors->has('password_confirmation') ? "is-invalid":"" }}" name="password_confirmation">
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i data-toggle="#password_confirmation" class="fa fa-fw fa-eye j a_toggle_password " role="button"></i>
                                        </span>
                                    </div>
                                    @if($errors->has('password_confirmation'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('password_confirmation') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="col text-right">
                                @can('admin.index')
                                    <a href="{{ route('admins.index') }}" class="btn btn-default mr-2">@lang('app.methods.cancel')</a>
                                @endcan
                                <button type="submit" class="btn btn-primary">@lang('app.methods.store')</button>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </section>

@endsection
@push('js')
    <script>


    </script>
@endpush
