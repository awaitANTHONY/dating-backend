$('#data-table').DataTable({
	processing: true,
	serverSide: true,
	ajax: _url + "/packages",
	"columns": [

		{ data: 'coins', name: 'coins' },
		{ data: 'amount', name: 'amount' },
		{ data: 'product_id', name: 'product_id' },
		{ data: 'status', name: 'status' },
		{ data: 'action', name: 'action', orderable: false, searchable: false, className: "text-center" },

	],
	responsive: true,
	"bStateSave": true,
	"bAutoWidth": false,
	"ordering": false
});