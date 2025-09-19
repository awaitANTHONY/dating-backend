<form class="ajax-submit2" method="post" autocomplete="off" action="{{ route('packages.update', $package->id) }}">
	@csrf
	@method('PUT')
	<div class="row">
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">Coins</label>
				<input type="number" class="form-control" name="coins" value="{{ $package->coins }}" required>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">Amount</label>
				<input type="number" step="0.01" class="form-control" name="amount" value="{{ $package->amount }}" required>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">Product Id</label>
				<input type="text" class="form-control" name="product_id" value="{{ $package->product_id }}" required>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">Status</label>
				<select class="form-control" name="status" data-selected="{{ $package->status }}" required>
					<option value="1" >Active</option>
					<option value="0" >In-Active</option>
				</select>
			</div>
		</div>
		<div class="col-md-12">
			<div class="form-group">
				<button type="reset" class="btn btn-danger btn-sm">{{ _lang('Reset') }}</button>
				<button type="submit" class="btn btn-primary btn-sm">{{ _lang('Save') }}</button>
			</div>
		</div>
	</div>
</form>



