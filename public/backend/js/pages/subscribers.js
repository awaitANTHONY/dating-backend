$('#data-table').DataTable({
		processing: true,
		serverSide: true,
		ajax: _url + "/subscribers",
		"columns" : [
			
			{ data : "redeem_code", name : "redeem_code", className : "redeem_code" },
        	{ data : "phone", name : "phone", className : "phone" },
        	{ data : "email", name : "email", className : "email" },
        	{ data : "expired_at", name : "expired_at", className : "expired_at" },
        	{ data : "status", name : "status", className : "status text-center" },
			{ data : "action", name : "action", orderable : false, searchable : false, className : "text-center" }
			
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,	
		"ordering": false
	});