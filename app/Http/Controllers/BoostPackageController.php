<?php

namespace App\Http\Controllers;

use App\Models\BoostPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class BoostPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $boostPackages = BoostPackage::orderBy('position', 'ASC')->get();

        if ($request->ajax()) {
            return DataTables::of($boostPackages)
                ->editColumn('platform', function ($package) {
                    return strtoupper($package->platform);
                })
                ->editColumn('boost_count', function ($package) {
                    return $package->boost_count . ' boosts';
                })
                ->addColumn('status', function ($package) {
                    return $package->status == 1 ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                })
                ->addColumn('action', function ($package) {
                    $action = '<div class="dropdown">
                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        ' . _lang('Action') . '
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('boost-packages.edit', $package->id) . '" class="dropdown-item" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                    $action .= '<form action="' . route('boost-packages.destroy', $package->id) . '" method="post" class="ajax-delete">'
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
                ->setRowData([
                    'id' => function ($package) {
                        return $package->id;
                    }
                ])
                ->rawColumns(['action', 'status'])
                ->make(true);
        }

        return view('backend.boost_packages.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.boost_packages.create');
        } else {
            return view('backend.boost_packages.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'boost_duration' => 'required|integer|min:15|max:240',
            'platform' => 'required|in:ios,android,both',
            'product_id' => 'required|string|unique:boost_packages,product_id',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $boostPackage = new BoostPackage();
        $boostPackage->name = $request->name;
        $boostPackage->description = $request->description;
        $boostPackage->boost_count = 1;
        $boostPackage->boost_duration = $request->boost_duration;
        $boostPackage->platform = $request->platform;
        $boostPackage->product_id = $request->product_id;
        $boostPackage->status = $request->status;
        $boostPackage->position = BoostPackage::max('position') + 1;
        $boostPackage->save();

        cache()->flush();

        if (!$request->ajax()) {
            return redirect('boost-packages')->with('success', _lang('Boost package added successfully!'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => url('boost-packages'), 'message' => _lang('Boost package added successfully!')]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $boostPackage = BoostPackage::find($id);

        if (!$request->ajax()) {
            return view('backend.boost_packages.show', compact('boostPackage'));
        } else {
            return view('backend.boost_packages.modal.show', compact('boostPackage'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $boostPackage = BoostPackage::find($id);

        if (!$request->ajax()) {
            return view('backend.boost_packages.edit', compact('boostPackage'));
        } else {
            return view('backend.boost_packages.modal.edit', compact('boostPackage'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'boost_duration' => 'required|integer|min:15|max:240',
            'platform' => 'required|in:ios,android,both',
            'product_id' => 'required|string|unique:boost_packages,product_id,' . $id,
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $boostPackage = BoostPackage::find($id);
        $boostPackage->name = $request->name;
        $boostPackage->description = $request->description;
        $boostPackage->boost_count = 1;
        $boostPackage->boost_duration = $request->boost_duration;
        $boostPackage->platform = $request->platform;
        $boostPackage->product_id = $request->product_id;
        $boostPackage->status = $request->status;
        $boostPackage->save();

        cache()->flush();

        if (!$request->ajax()) {
            return redirect('boost-packages')->with('success', _lang('Boost package updated successfully!'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => url('boost-packages'), 'message' => _lang('Boost package updated successfully!')]);
        }
    }

    /**
     * Reorder boost packages
     */
    public function reorder(Request $request)
    {
        $packages = json_decode($request->packages);

        foreach ($packages as $package_data) {
            $package = BoostPackage::find($package_data->id);
            $package->position = $package_data->position;
            $package->save();
        }

        if (!$request->ajax()) {
            return redirect('boost-packages')->with('success', _lang('Packages reordered successfully!'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Packages reordered successfully!')]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $boostPackage = BoostPackage::find($id);
        $boostPackage->delete();

        cache()->flush();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Boost package deleted successfully!'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Boost package deleted successfully!')]);
        }
    }
}
