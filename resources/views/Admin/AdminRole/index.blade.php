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
                        <li class="breadcrumb-item active">@lang('class.roles')@lang('app.methods.list')</li>
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
                        <h3 class="card-title">@lang('class.roles')@lang('app.methods.list')</h3>
                        @can('role.create')
                            <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm float-right">@lang('app.methods.create')</a>
                        @endcan
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">
                            <table id="dataTable" class="table table-bordered custom-table">
                                <thead class="bg-gray">
                                <tr>
                                    <th>@lang('property.roles..name')</th>
                                    <th>@lang('class.permissions')</th>
                                    <th class="w-25">@lang('app.methods.actions')</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($paginator as $role)
                                    <tr>
                                        <td>{{ $role->name }}</td>
                                        <td>
                                            @foreach($role->permissions as $permission)
                                                <span class="badge badge-primary">{{ $permission->title }} </span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @can('role.edit')
                                                <a href="{{ route('roles.edit',$role->id) }}" type="button" class="btn btn-info btn-sm">@lang('app.methods.edit')</a>
                                            @endcan

                                            @can('role.destroy')
                                                <form action="{{ route('roles.destroy',$role->id) }}" method="post" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="if (confirm('@lang('app.methods.delete_confirm')')) { this.form.submit() } "> @lang('app.methods.delete')</button>
                                                </form>
                                            @endcan
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
                    <!-- /.card-body -->

                    <div class="card-footer">
                        @include('module.iterm_link')
                    </div>
                </div>
                <!-- /.card -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>
    <!-- /.content -->
@endsection
