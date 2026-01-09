$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/account-delete-requests",
    "columns": [
        { data: "email", name: "email", className: "email" },
        { data: "type", name: "type", className: "type" },
        { data: "accepted", name: "accepted", className: "accepted" },
        { data: "created_at", name: "created_at", className: "created_at" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
