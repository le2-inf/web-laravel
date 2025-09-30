@extends('layouts.app')

@section('content')

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
                        <li class="breadcrumb-item active">@lang('app.methods.create')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>


    <section class="content">
        <div class="row">
            <div class="col-sm-12">
                <form action="{{ route('roles.store') }}" method="post">
                    @csrf
                    <div class="card">

                        <div class="card-header">
                            <h3 class="card-title">@lang('class.roles')@lang('app.methods.create')</h3>
                        </div>

                        <div class="card-body">
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.roles..name')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="text" name="name" class="form-control {{ $errors->has('name') ? "is-invalid":"" }}" value="{{ old('name') }}">
                                    @if($errors->has('name'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('name') }}</strong></span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label">@lang('class.permissions')</label>
                                <div class="col-sm-8 col-xl-6 input-group">
                                    <select multiple="multiple" name="permissions[]" size="20" class="duallistbox" aria-multiselectable="true">
                                        @foreach($permissions as $permission)
                                            <option value="{{ $permission->name }}">{{ $permission->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                        <div class=" card-footer">
                            <div class="col text-right">
                                @can('role.index')
                                    <a href="{{ route('roles.index') }}" class="btn btn-default mr-2">@lang('app.methods.cancel')</a>
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
