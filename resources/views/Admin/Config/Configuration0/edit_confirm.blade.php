@extends('layouts.app')

@section('content')
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.cb_configurations')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">
                            @can(sprintf('config%d.index',$uc))
                                <a href="{{ route( sprintf('config%d.index',$uc)) }}">@lang('class.cb_configurations') ( {{ $uc_label }} ) </a>
                            @endcan
                        </li>
                        <li class="breadcrumb-item active"> @lang('app.methods.edit_confirm')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-12">
                <form method="post" action="{{route(sprintf('config%d.update',$uc),array('configuration'=>$configuration->cfg_key))}}" class="j a_confirm_form">
                    @method('put')
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                @lang('app.methods.edit_confirm'){{ $uc_label }}@lang('class.cb_configurations')
                            </h3>
                        </div>
                        <div class="card-body">

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..cfg_key')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    {{ $configuration['cfg_key'] }}
                                    <input type="hidden" value="{{ $configuration['cfg_key'] }}" name="cfg_key"/>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..cfg_value')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    {{ $configuration->cfg_value_label }}
                                    <input type="hidden" value="{{ $configuration['cfg_value'] }}" name="cfg_value"/>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..usage_category')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    {{ $uc_label }}
                                    <input type="hidden" class="form-control" value="{{ $uc }}" name="usage_category"/>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..masked')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    {{ $configuration->masked_label }}
                                    <input type="hidden" value="{{ $configuration->masked }}" name="masked"/>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..description')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    {{ $configuration['description'] }}
                                    <input type="hidden" name="description" class="form-control" value="{{ $configuration['description'] }}"/>
                                </div>
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            @can(sprintf('config%d.edit',$uc))
                                <a class="btn btn-default mr-2" href="{{ route(sprintf('config%d.edit',$uc),['configuration'=>$configuration->cfg_key]) }}">@lang('app.methods.cancel')</a>
                            @endcan
                            <button type="submit" class="btn btn-primary">@lang('app.methods.update')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
@stop
