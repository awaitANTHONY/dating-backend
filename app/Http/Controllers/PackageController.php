<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::orderBy('id', 'DESC')->get();
        if ($request->ajax()) {
            return DataTables::of($packages)
                ->addColumn('status', function ($package) {
                    return $package->status == 1 ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                })
                ->addColumn('action', function($package){
                    $action = '<div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        ' . _lang('Action') . '
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('packages.edit', $package->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                    $action .= '<form action="' . route('packages.destroy', $package->id) . '" method="post" class="ajax-delete" style="display:inline;">'
                                . csrf_field() 
                                . method_field('DELETE') 
                                . '<button type="button" class="btn-remove dropdown-item">
                                        <i class="fas fa-trash-alt"></i>
                                        ' . _lang('Delete') . '
                                    </button>
                                </form>';
                    $action .= '</div>
                                </div>';
                    return $action;
                })
                ->setRowData(['id' => function ($package) { return $package->id; }])
                ->rawColumns(['action', 'status'])
                ->make(true);
        }
        return view('backend.packages.index');
    }

    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.packages.create');
        } else {
            return view('backend.packages.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coins' => 'required|integer',
            'amount' => 'required|numeric',
            'product_id' => 'required|string',
            'status' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }
        $package = Package::create($request->only(['coins','amount','product_id','status']));
        if (!$request->ajax()) {
            return redirect('packages')->with('success', _lang('Information added sucessfully!'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => url('packages'), 'message' => _lang('Information added sucessfully!')]);
        }
    }

    public function edit(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        if (!$request->ajax()) {
            return view('backend.packages.edit', compact('package'));
        } else {
            return view('backend.packages.modal.edit', compact('package'));
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'coins' => 'required|integer',
            'amount' => 'required|numeric',
            'product_id' => 'required|string',
            'status' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }
        $package = Package::findOrFail($id);
        $package->update($request->only(['coins','amount','product_id','status']));
        if (!$request->ajax()) {
            return redirect('packages')->with('success', _lang('Information updated sucessfully!'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => url('packages'), 'message' => _lang('Information updated sucessfully!')]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $package = Package::findOrFail($id);
        $package->delete();
        if (!$request->ajax()) {
            return back()->with('success', _lang('Information deleted sucessfully!'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Information deleted sucessfully!')]);
        }
    }
}
