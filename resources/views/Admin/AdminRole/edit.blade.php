@extends('layouts.app')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.roles')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item">
                            @can('role.index')
                                <a href="{{ route('roles.index') }}">@lang('class.roles')@lang('app.methods.list')</a>
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

                <form action="{{ route('roles.update',$role->id) }}" method="post">
                    @csrf
                    @method('PUT')
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">@lang('class.roles')@lang('app.methods.edit')</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.roles..name')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? "is-invalid":"" }}" value="{{ old('name',$role->name) }}">
                                    @if($errors->has('name'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('name') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label">@lang('class.permissions')</label>
                                <div class="col-sm-8 col-xl-6 input-group">
                                    <select multiple="multiple" name="permissions[]" size="20" class="duallistbox" aria-multiselectable="true">
                                        @foreach($PermissionOptions as $permission)
                                            <option value="{{ $permission->name }}" @selected($role->hasPermissionTo($permission->name)) >{{ $permission->title   }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer">

                            <div class="col text-right">
                                @can('role.index')
                                    <a href="{{ route('roles.index') }}" class="btn btn-default mr-2">@lang('app.methods.cancel')</a>
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
