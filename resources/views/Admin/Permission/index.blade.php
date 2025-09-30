@extends('layouts.app')

@section('content')

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.permissions')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">@lang('class.permissions')@lang('app.methods.list')</li>
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
                        <h3 class="card-title">@lang('class.permissions')@lang('app.methods.list')</h3>
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">
                            <table id="dataTable" class="table table-bordered table-striped custom-table">
                                <thead class="bg-gray">
                                <tr>
                                    <th>@lang('property.permissions..name')</th>
                                    <th>@lang('class.permissions.fields')</th>
                                    <th>@lang('class.roles')</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($paginator as $permission)
                                    <tr>
                                        <td>{{ $permission->name }}</td>
                                        <td>{{ $permission->title }}</td>
                                        <td>
                                            @foreach($permission->roles as $role)
                                                <span class="badge badge-success">{{ $role->name }} </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach

                                @if($paginator->total() == 0)
                                    <tr>
                                        <td colspan="5">@lang('app.methods.result_empty')</td>
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
    <!-- /.content -->
@endsection
