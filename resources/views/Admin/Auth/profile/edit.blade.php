@extends('layouts.app')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">@lang('class.admins')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>


    <section class="content">
        <div class="row">
            <div class="col-sm-12">

                <form action="{{ route('profile.update') }}" method="post">
                    @csrf
                    @method('PUT')

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">@lang('property.Admin.password')@lang('app.methods.edit')</h3>
                        </div>

                        <div class="card-body">


                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.password')</label>
                                <div class="col-sm-8 col-lg-5 input-group">
                                    <input type="password" name="password" id="password-field" class="form-control {{ $errors->has('password') ? "is-invalid":"" }}">
                                    <div class="input-group-append">
                                        <span class="input-group-text">
                                            <i data-toggle="#password_confirmation" class="fa fa-fw fa-eye j a_toggle_password " role="button"></i>
                                        </span>
                                    </div>
                                    @if($errors->has('password'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('password') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.Admin.password_confirmation')</label>
                                <div class="col-sm-8 col-lg-5 input-group">
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
                                @can('home')
                                    <a href="{{ route('home') }}" class="btn btn-default mr-2">@lang('app.methods.cancel')</a>
                                @endcan
                                <button type="submit" class="btn btn-primary">@lang('app.methods.update')</button>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </section>

@endsection
