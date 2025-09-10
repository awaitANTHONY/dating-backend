$('#data-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: _url + "/faqs",
    "columns": [
        { data: "question", name: "question", className: "question" },
        { data: "answer", name: "answer", className: "answer" },
        { data: "status", name: "status", className: "status" },
        { data: "action", name: "action", orderable: false, searchable: false, className: "text-center" }
    ],
    responsive: true,
    "bStateSave": true,
    "bAutoWidth": false,
    "ordering": false
});
