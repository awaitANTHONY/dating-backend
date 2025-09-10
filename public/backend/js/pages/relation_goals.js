$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/relation_goals",
    "columns": [
        { data: "title", name: "title", className: "title" },
        { data: "subtitle", name: "subtitle", className: "subtitle" },
        { data: "status", name: "status", className: "status" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
