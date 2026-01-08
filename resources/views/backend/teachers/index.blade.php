@extends('backend.layouts.app')

@section('title', __('labels.backend.teachers.title').' | '.app_name())

@push('after-styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<style>
.switch.switch-3d.switch-lg {
    width: 40px;
    height: 20px;
}
.switch.switch-3d.switch-lg .switch-handle {
    width: 20px;
    height: 20px;
}
</style>
@endpush

@section('content')

<div>
    <div class="d-flex justify-content-between pb-3 align-items-center">
        <h4>Trainers</h4>

        @can('trainer_create')
        <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">
            Add
        </a>
        @endcan
    </div>

    <div class="card border-0">
        <div class="card-body">

            <ul class="list-inline mb-3">
                <li class="list-inline-item">
                    <a href="{{ route('admin.teachers.index') }}"
                       style="{{ request('show_deleted') ? '' : 'font-weight:700' }}">
                        {{ __('labels.general.all') }}
                    </a>
                </li>
                |
                <li class="list-inline-item">
                    <a href="{{ route('admin.teachers.index',['show_deleted'=>1]) }}"
                       style="{{ request('show_deleted') ? 'font-weight:700' : '' }}">
                        {{ __('labels.general.trash') }}
                    </a>
                </li>
            </ul>

            <div class="table-responsive">
                <table id="myTable" class="table table-striped">
                    <thead>
                    <tr>
                        @if(request('show_deleted') != 1)
                        <th>
                            <input type="checkbox" id="select-all">
                        </th>
                        @endif
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                </table>
            </div>

        </div>
    </div>
</div>

@endsection

@push('after-scripts')

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<!-- Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<!-- Export libs -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
$(function () {

    let route = "{{ route('admin.teachers.get_data') }}";

    @if(request('show_deleted') == 1)
        route = "{{ route('admin.teachers.get_data',['show_deleted'=>1]) }}";
    @endif

    let table = $('#myTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 10,

        dom:
            "<'d-flex justify-content-between align-items-center mb-2'lfB>" +
            "t" +
            "<'d-flex justify-content-between align-items-center mt-3'ip>",

        buttons: [
            {
                extend: 'collection',
                text: '<i class="fa fa-download"></i>',
                buttons: ['csv', 'pdf']
            },
            {
                extend: 'colvis',
                text: '<i class="fa fa-eye"></i>'
            }
        ],

        ajax: route,

        columns: [
            @if(request('show_deleted') != 1)
            {
                data: function (row) {
                    return `<input type="checkbox" class="single" value="${row.id}">`;
                },
                orderable: false,
                searchable: false
            },
            @endif
            { data: 'id' },
            { data: 'first_name' },
            { data: 'last_name' },
            { data: 'email' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false }
        ],

        columnDefs: [
            {
                targets: -1,
                className: 'text-center'
            }
        ]
    });

    // Status switch
    $(document).on('click', '.switch-input', function () {
        let id = $(this).data('id');

        $.post("{{ route('admin.teachers.status') }}", {
            _token: "{{ csrf_token() }}",
            id: id
        }, function () {
            table.ajax.reload(null, false);
        });
    });

});
</script>

@endpush
