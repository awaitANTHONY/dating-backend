$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/gifts",
    "columns": [
        { data: "coin", name: "coin", className: "coin" },
        { data: "image", name: "image", className: "image" },
        { data: "status", name: "status", className: "status" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});