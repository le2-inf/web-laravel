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
                            @lang('class.cb_configurations') ( {{ $uc_label ?? '' }} )
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            @lang('class.cb_configurations') ( {{ $uc_label ?? '' }} )
                        </h3>
                        @can(sprintf('config%d.create',$uc ?? ''))
                            <a class="btn btn-primary btn-sm float-right" href="{{route( sprintf('config%d.create',$uc ?? ''))}}">@lang('app.methods.create')</a>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover custom-table break-table">
                                <thead class="bg-gray">
                                <tr>
                                    <th>@lang('property.cb_configurations..cfg_key')</th>
                                    <th>@lang('property.cb_configurations..cfg_value')</th>
                                    <th>@lang('property.cb_configurations..usage_category')</th>
                                    <th>@lang('property.cb_configurations..masked')</th>
                                    <th>@lang('property.cb_configurations..description')</th>
                                    <th>@lang('property.cb_configurations..updated_at')</th>
                                    <th>@lang('app.methods.actions')</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($data as $item)
                                    <tr>
                                        <td style="min-width: 16rem;">{{ $item->cfg_key }}</td>
                                        <td style="min-width: 24rem;">{{ $item->cfg_value_label }}</td>
                                        <td>{{ $item->usage_category_label }}</td>
                                        <td>{{ $item->masked_label }}</td>
                                        <td style="min-width: 12rem;">{{ $item->description }}</td>
                                        <td>{{ $item->updated_at_label }}</td>
                                        <td class="text-nowrap">
                                            @can(sprintf('config%d.edit',$uc))
                                                <a href="{{route( sprintf('config%d.edit',$uc),array('configuration'=>$item->cfg_key))}}" class="btn btn-info btn-sm">@lang('app.methods.edit')</a>
                                            @endcan
                                            @can(sprintf('config%d.destroy',$uc))
                                                <form action="{{ route(sprintf('config%d.destroy',$uc),$item->cfg_key) }}" method="post" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class=" btn btn-danger btn-sm" onclick="if (confirm('@lang('app.methods.delete_confirm')')) { this.form.submit() } ">@lang('app.methods.delete')</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach

                                @if($paginator->total() == 0)
                                    <tr>
                                        <td colspan="7">@lang('global.result_empty')</td>
                                    </tr>
                                @endif

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer">
                        @include('module.iterm_link')
                    </div>
                </div>
            </div>
        </div>
    </section>
@stop
