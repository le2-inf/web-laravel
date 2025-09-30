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
                        <li class="breadcrumb-item active"> @lang('app.methods.create')</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <form method="post" action="{{route( sprintf('config%d.create_confirm',$uc))}}">
                    @csrf
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                @lang('app.methods.create'){{ $uc_label }}@lang('class.cb_configurations')
                            </h3>
                        </div>
                        <div class="card-body">

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..cfg_key')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input class="form-control" value="{{old('cfg_key')}}" name="cfg_key"/>
                                    @if($errors->has('cfg_key'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('cfg_key') }}</strong></span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..cfg_value')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input class="form-control" value="{{old('cfg_value')}}" name="cfg_value"/>
                                    @if($errors->has('cfg_value'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('cfg_value') }}</strong></span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..usage_category')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <input type="hidden" class="form-control" value="{{ $uc }}" name="usage_category"/>
                                    <span class="col-form-label noc">{{ $uc_label }}</span>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..masked')</label>
                                <div class="col-sm-8 col-xl-4">
                                    <div class="form-group">
                                        @foreach(\App\Enum\Config\CfgMasked::LABELS as $key=>$label)
                                            <div class="form-check col-4">
                                                <input class="form-check-input" type="radio" name="masked" id="masked_{{ $key }}" value="{{ $key }}" @checked( old('masked') === $key )>
                                                <label class="form-check-label" for="masked_{{ $key }}">{{ $label }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if($errors->has('masked'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('masked') }}</strong></span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-xl-3 col-form-label required">@lang('property.cb_configurations..description')</label>
                                <div class="col-sm-8 col-xl-4 input-group">
                                    <textarea name="description" class="form-control">{{old('description')}}</textarea>
                                    @if($errors->has('description'))
                                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $errors->first('description') }}</strong></span>
                                    @endif
                                </div>
                            </div>

                        </div>

                        <div class="card-footer">
                            <div class="col text-right">
                                @can(sprintf('config%d.index',$uc))
                                    <a class="btn btn-default mr-2" href="{{ route( sprintf('config%d.index',$uc)) }}">@lang('app.methods.cancel')</a>
                                @endcan
                                <button type="submit" class="btn btn-primary">@lang('app.methods.confirm')</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </section>
@stop
