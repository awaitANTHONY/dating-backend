$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/mood_suggestions",
    "columns": [
        { data: "text", name: "text" },
        { data: "sort_order", name: "sort_order" },
        { data: "is_active", name: "is_active" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
