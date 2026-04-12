@extends('layouts.app')

@section('content')
<style>
    #review-popup {
        display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background:rgba(0,0,0,0.6); z-index:9999;
    }
    #review-popup .popup-box {
        position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
        background:#fff; border-radius:10px; width:90%; max-width:750px;
        max-height:90vh; overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,0.3);
    }
    #review-popup .popup-header {
        padding:15px 20px; border-bottom:1px solid #eee;
        display:flex; justify-content:space-between; align-items:center;
    }
    #review-popup .popup-body { padding:20px; }
    #review-popup .popup-footer {
        padding:15px 20px; border-top:1px solid #eee;
        display:flex; gap:10px; justify-content:center;
    }
    #review-popup .photo-grid { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
    #review-popup .photo-grid img { width:100px; height:100px; object-fit:cover; border-radius:6px; cursor:pointer; border:2px solid #ddd; }
    #review-popup .selfie-img { max-height:220px; max-width:100%; object-fit:contain; border-radius:6px; border:2px solid #ddd; cursor:pointer; }
    #review-popup .close-btn { background:none; border:none; font-size:24px; cursor:pointer; color:#666; }
    #review-popup .close-btn:hover { color:#000; }
    #review-popup .info-row { display:flex; gap:15px; flex-wrap:wrap; margin-top:12px; }
    #review-popup .info-item { font-size:13px; color:#666; }
    #review-popup .info-item strong { color:#333; }
</style>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="header-title mb-0">
                    <i class="fas fa-user-check mr-2"></i>Verification Review Queue
                    <span id="queue-count" class="badge badge-warning ml-2" style="font-size:14px;">{{ $reviewCount }}</span>
                </h4>
                <div>
                    <button class="btn btn-success btn-sm" id="btn-bulk-approve" style="display:none;">
                        <i class="fas fa-check-double"></i> Approve Selected (<span id="selected-count">0</span>)
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btn-refresh">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($reviewCount == 0)
                <div id="empty-state" class="text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size:48px;"></i>
                    <h5 class="mt-3 text-muted">All clear! No pending reviews.</h5>
                </div>
                @endif

                <div class="table-responsive" id="table-wrapper" @if($reviewCount == 0) style="display:none;" @endif>
                    <table class="table table-hover" id="verification-queue-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="select-all"></th>
                                <th>Profile</th>
                                <th>Selfie</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Country</th>
                                <th>Face Match</th>
                                <th>Matched</th>
                                <th>Waiting</th>
                                <th style="width:150px;">Action</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Simple popup overlay (no Bootstrap modal) -->
<div id="review-popup">
    <div class="popup-box">
        <div class="popup-header">
            <h5 style="margin:0;"><i class="fas fa-user-check mr-2"></i>Review Verification</h5>
            <button class="close-btn" id="popup-close">&times;</button>
        </div>
        <div class="popup-body">
            <div id="popup-loading" class="text-center py-4">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">Loading...</p>
            </div>
            <div id="popup-data" style="display:none;">
                <!-- Photos side by side -->
                <div class="row">
                    <div class="col-5 text-center">
                        <small class="text-muted d-block mb-2">Profile Photos</small>
                        <div class="photo-grid" id="popup-photos"></div>
                    </div>
                    <div class="col-2 d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <span id="popup-score" class="badge" style="font-size:18px;">-</span>
                            <br><small class="text-muted">Match</small>
                            <br><small class="text-muted" id="popup-matched"></small>
                        </div>
                    </div>
                    <div class="col-5 text-center">
                        <small class="text-muted d-block mb-2">Selfie</small>
                        <img id="popup-selfie" src="" class="selfie-img" onclick="window.open(this.src,'_blank')">
                    </div>
                </div>

                <!-- User info -->
                <div class="info-row">
                    <div class="info-item"><strong>Name:</strong> <span id="popup-name"></span></div>
                    <div class="info-item"><strong>Gender:</strong> <span id="popup-gender"></span></div>
                    <div class="info-item"><strong>Age:</strong> <span id="popup-age"></span></div>
                    <div class="info-item"><strong>Country:</strong> <span id="popup-country"></span></div>
                    <div class="info-item"><strong>Waiting:</strong> <span id="popup-waiting"></span></div>
                </div>

                <!-- Reason -->
                <div class="alert alert-warning mt-3 mb-0" style="font-size:13px;">
                    <strong>Review Reason:</strong> <span id="popup-reason"></span>
                </div>
            </div>
        </div>
        <div class="popup-footer" id="popup-actions" style="display:none;">
            <button type="button" class="btn btn-success btn-lg" id="popup-approve" style="min-width:180px;">
                <i class="fas fa-check mr-1"></i> Approve
            </button>
            <button type="button" class="btn btn-danger btn-lg" id="popup-reject" style="min-width:180px;">
                <i class="fas fa-times mr-1"></i> Reject
            </button>
        </div>
    </div>
</div>

@endsection

@section('js-script')
<script>
$(document).ready(function() {
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var currentId = null;

    var table = $('#verification-queue-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: '{{ url("verification-queue") }}', type: 'GET' },
        order: [[9, 'asc']], pageLength: 25,
        columns: [
            { data: null, orderable:false, searchable:false, render: function(d) { return '<input type="checkbox" class="row-select" value="'+d.id+'">'; } },
            { data:'user_image', orderable:false, searchable:false },
            { data:'selfie', orderable:false, searchable:false },
            { data:'user_name', name:'user.name' },
            { data:'gender', orderable:false, searchable:false },
            { data:'age', orderable:false, searchable:false },
            { data:'country', orderable:false, searchable:false },
            { data:'face_score', orderable:false, searchable:false },
            { data:'matched_count', orderable:false, searchable:false },
            { data:'waiting', orderable:false, searchable:false },
            { data:'action', orderable:false, searchable:false }
        ],
        language: { emptyTable: "No verifications awaiting review." }
    });

    // Select all
    $('#select-all').on('change', function() { $('.row-select').prop('checked', this.checked); updateBulk(); });
    $(document).on('change', '.row-select', function() { updateBulk(); });
    function updateBulk() {
        var c = $('.row-select:checked').length;
        $('#selected-count').text(c);
        $('#btn-bulk-approve').toggle(c > 0);
    }

    // Inline approve
    $(document).on('click', '.btn-approve', function() {
        var id = $(this).data('id'), btn = $(this);
        btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('/verification-queue/'+id+'/approve', {_token:csrf}, function(res) {
            if(res.result==='success') { toastr.success(res.message); table.ajax.reload(null,false); $('#queue-count').text(res.remaining); if(res.remaining==0){$('#table-wrapper').hide();$('#empty-state').show();} }
            else { toastr.error(res.message); btn.prop('disabled',false).html('<i class="fas fa-check"></i>'); }
        }).fail(function(){ toastr.error('Failed'); btn.prop('disabled',false).html('<i class="fas fa-check"></i>'); });
    });

    // Inline reject
    $(document).on('click', '.btn-reject', function() {
        var id = $(this).data('id'), btn = $(this);
        if(!confirm('Reject this verification?')) return;
        btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('/verification-queue/'+id+'/reject', {_token:csrf}, function(res) {
            if(res.result==='success') { toastr.warning(res.message); table.ajax.reload(null,false); $('#queue-count').text(res.remaining); if(res.remaining==0){$('#table-wrapper').hide();$('#empty-state').show();} }
            else { toastr.error(res.message); btn.prop('disabled',false).html('<i class="fas fa-times"></i>'); }
        }).fail(function(){ toastr.error('Failed'); btn.prop('disabled',false).html('<i class="fas fa-times"></i>'); });
    });

    // ── POPUP: Open ──
    $(document).on('click', '.btn-detail', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        currentId = id;
        $('#popup-loading').show();
        $('#popup-data').hide();
        $('#popup-actions').hide();
        $('#review-popup').fadeIn(200);

        $.get('/verification-queue/' + id, function(d) {
            // Selfie
            $('#popup-selfie').attr('src', d.selfie_url);

            // Profile photos
            var grid = $('#popup-photos');
            grid.empty();
            if (d.profile_photos && d.profile_photos.length) {
                d.profile_photos.forEach(function(url) {
                    grid.append('<img src="'+url+'" onclick="window.open(this.src)">');
                });
            } else {
                grid.html('<span class="text-muted">No photos</span>');
            }

            // Score
            var sc = $('#popup-score');
            if (d.highest_match !== null) {
                var v = parseFloat(d.highest_match);
                var c = v >= 80 ? 'success' : (v >= 60 ? 'warning' : 'danger');
                sc.text(v.toFixed(1)+'%').attr('class','badge badge-'+c).css('font-size','18px');
            } else {
                sc.text('N/A').attr('class','badge badge-secondary').css('font-size','18px');
            }

            $('#popup-matched').text(d.matched_count || '');
            $('#popup-name').text(d.user_name);
            $('#popup-gender').text(d.gender);
            $('#popup-age').text(d.age || '-');
            $('#popup-country').text(d.country);
            $('#popup-waiting').text(d.waiting);
            $('#popup-reason').text(d.reason);

            $('#popup-loading').hide();
            $('#popup-data').show();
            $('#popup-actions').show();
        }).fail(function() {
            $('#popup-loading').html('<div class="alert alert-danger">Failed to load.</div>');
        });
    });

    // ── POPUP: Close ──
    $('#popup-close').on('click', function() { $('#review-popup').fadeOut(200); currentId = null; });
    $('#review-popup').on('click', function(e) { if(e.target === this) { $(this).fadeOut(200); currentId = null; } });

    // ── POPUP: Approve ──
    $('#popup-approve').on('click', function() {
        if(!currentId) return;
        var btn = $(this);
        btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Approving...');
        $.post('/verification-queue/'+currentId+'/approve', {_token:csrf}, function(res) {
            if(res.result==='success') {
                toastr.success(res.message);
                $('#review-popup').fadeOut(200);
                table.ajax.reload(null,false);
                $('#queue-count').text(res.remaining);
                if(res.remaining==0){$('#table-wrapper').hide();$('#empty-state').show();}
            } else { toastr.error(res.message); }
            btn.prop('disabled',false).html('<i class="fas fa-check mr-1"></i> Approve');
        }).fail(function(){ toastr.error('Failed'); btn.prop('disabled',false).html('<i class="fas fa-check mr-1"></i> Approve'); });
    });

    // ── POPUP: Reject ──
    $('#popup-reject').on('click', function() {
        if(!currentId) return;
        if(!confirm('Reject this verification? The user will be notified.')) return;
        var btn = $(this);
        btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Rejecting...');
        $.post('/verification-queue/'+currentId+'/reject', {_token:csrf}, function(res) {
            if(res.result==='success') {
                toastr.warning(res.message);
                $('#review-popup').fadeOut(200);
                table.ajax.reload(null,false);
                $('#queue-count').text(res.remaining);
                if(res.remaining==0){$('#table-wrapper').hide();$('#empty-state').show();}
            } else { toastr.error(res.message); }
            btn.prop('disabled',false).html('<i class="fas fa-times mr-1"></i> Reject');
        }).fail(function(){ toastr.error('Failed'); btn.prop('disabled',false).html('<i class="fas fa-times mr-1"></i> Reject'); });
    });

    // Bulk approve
    $('#btn-bulk-approve').on('click', function() {
        var ids = []; $('.row-select:checked').each(function(){ids.push($(this).val());});
        if(!ids.length) return;
        if(!confirm('Approve '+ids.length+' verification(s)?')) return;
        var btn = $(this);
        btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        $.post('/verification-queue/bulk-approve', {_token:csrf, ids:ids}, function(res) {
            if(res.result==='success') { toastr.success(res.message); table.ajax.reload(null,false); $('#queue-count').text(res.remaining); $('#select-all').prop('checked',false); updateBulk(); if(res.remaining==0){$('#table-wrapper').hide();$('#empty-state').show();} }
            else { toastr.error(res.message); }
            btn.prop('disabled',false).html('<i class="fas fa-check-double"></i> Approve Selected (<span id="selected-count">0</span>)');
        }).fail(function(){ toastr.error('Failed'); btn.prop('disabled',false).html('<i class="fas fa-check-double"></i> Approve Selected'); });
    });

    // Refresh
    $('#btn-refresh').on('click', function() { table.ajax.reload(null,false); });

    // Keyboard: press A to approve in popup
    $(document).on('keydown', function(e) {
        if($('#review-popup').is(':visible') && currentId) {
            if(e.key==='a'||e.key==='A') { e.preventDefault(); $('#popup-approve').click(); }
            if(e.key==='Escape') { $('#review-popup').fadeOut(200); currentId=null; }
        }
    });
});
</script>
@endsection
