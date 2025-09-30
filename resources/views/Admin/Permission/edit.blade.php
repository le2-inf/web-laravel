@extends('layouts.app')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.permissions')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            @can('permission.index')
                                <a href="{{ route('permissions.index') }}">@lang('class.permissions')@lang('app.methods.list')</a>
                            @endcan
                        </li>
                        <li class="breadcrumb-item active">@lang('app.methods.edit')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-sm-12">
                <form action="{{ route('permissions.update',$permission->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">@lang('class.permissions')@lang('app.methods.edit')</h3>
                        </div>

                        <div class="card-body">
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.permissions..name')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? "is-invalid":"" }}" value="{{ old('name',$permission->name) }}">
                                    @if($errors->has('name'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('name') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label">@lang('class.permissions.fields')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="text" name="title" class="form-control" value="{{ old('title',$permission->title) }}">
                                    @if($errors->has('title'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('title') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <div class="col text-right">
                                @can('permission.index')
                                    <a href="{{ route('permissions.index') }}" class="btn btn-default mr-2">@lang('app.methods.cancel')</a>
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
