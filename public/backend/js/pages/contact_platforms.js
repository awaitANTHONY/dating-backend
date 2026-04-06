$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/contact_platforms",
    "columns": [
        { data: "icon", name: "icon", orderable: false, searchable: false },
        { data: "name", name: "name" },
        { data: "placeholder", name: "placeholder" },
        { data: "status", name: "status" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
