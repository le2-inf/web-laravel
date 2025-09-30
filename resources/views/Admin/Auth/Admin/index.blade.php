@extends('layouts.app')

@section('content')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>@lang('class.admins')</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active">@lang('class.admins')@lang('app.methods.list')</li>
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
                        <h3 class="card-title">@lang('class.admins')@lang('app.methods.list')</h3>
                        @can('admin.create')
                            <a href="{{ route('admins.create') }}" class="btn btn-primary btn-sm float-right">@lang('app.methods.create')</a>
                        @endcan
                    </div>

                    <div class="card-body">

                        <div class="table-responsive">
                            <table id="dataTable" class="table table-bordered custom-table">
                                <thead class="bg-gray">
                                <tr>
                                    <th>@lang('property.Admin.id')</th>
                                    <th>@lang('property.Admin.name')</th>
                                    <th>@lang('property.Admin.email')</th>
                                    <th>@lang('class.roles')</th>
                                    {{--                                    <th>@lang('class.permissions')</th>--}}
                                    <th class="w-25">@lang('app.methods.actions')</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($data as $admin)
                                    <tr>
                                        <td>{{ $admin->id }}</td>
                                        <td>{{ $admin->name }}</td>
                                        <td>{{ $admin->email }}</td>
                                        <td>
                                            @foreach($admin->roles()->pluck('name') as $role)
                                                <span class="badge badge-primary">{{ $role }} </span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @can('admin.edit')
                                                <a href="{{ route('admins.edit',$admin->id) }}" type="button" class="btn btn-info btn-sm">@lang('app.methods.edit')</a>
                                            @endcan

                                            @can('admin.destroy')
                                                <form action="{{ route('admins.destroy',$admin->id) }}" method="post" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="if (confirm('@lang('app.methods.delete_confirm')')) { this.form.submit() } "> @lang('app.methods.delete')</button>
                                                </form>
                                            @endcan

                                        </td>
                                    </tr>
                                @endforeach
                                @if($data->total() == 0)
                                    <tr>
                                        <td colspan="6">@lang('app.methods.result_empty')</td>
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
