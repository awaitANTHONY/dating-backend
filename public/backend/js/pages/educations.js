$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/educations",
    "columns": [
        { data: "title", name: "title", className: "title" },
        { data: "status", name: "status", className: "status" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }

    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
