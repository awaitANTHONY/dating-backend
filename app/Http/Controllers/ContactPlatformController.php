<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ContactPlatform;
use DataTables;
use Validator;

class ContactPlatformController extends Controller
{
    public function index(Request $request)
    {
        $query = ContactPlatform::orderBy('sort_order', 'ASC');

        if ($request->ajax()) {
            return DataTables::of($query)
                ->editColumn('icon', function ($platform) {
                    if ($platform->icon) {
                        return '<img src="' . asset($platform->icon) . '" alt="" style="width:30px;height:30px;">';
                    }
                    return '<i class="fas fa-link"></i>';
                })
                ->editColumn('status', function ($platform) {
                    return ($platform->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                })
                ->addColumn('action', function ($platform) {
                    $action = '<div class="dropdown">';
                    $action .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                    $action .= _lang('Action');
                    $action .= '</button>';
                    $action .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('contact_platforms.edit', $platform->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit Contact Platform') . '"><i class="fas fa-edit"></i> ' . _lang('Edit') . '</a>';
                    $action .= '<form action="' . route('contact_platforms.destroy', $platform->id) . '" method="post" class="ajax-delete">' . csrf_field() . method_field('DELETE') . '<button type="button" class="btn-remove dropdown-item"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button></form>';
                    $action .= '</div></div>';
                    return $action;
                })
                ->rawColumns(['action', 'status', 'icon'])
                ->make(true);
        }

        return view('backend.contact_platforms.index');
    }

    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.contact_platforms.create');
        } else {
            return view('backend.contact_platforms.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:50',
            'icon'        => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $platform = new ContactPlatform();
        $platform->name = $request->name;
        $platform->icon = $request->icon;
        $platform->placeholder = $request->placeholder;
        $platform->sort_order = $request->sort_order ?? 0;
        $platform->status = $request->status;
        $platform->save();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $platform]);
        }
    }

    public function edit(Request $request, $id)
    {
        $platform = ContactPlatform::findOrFail($id);

        if (!$request->ajax()) {
            return view('backend.contact_platforms.edit', compact('platform'));
        } else {
            return view('backend.contact_platforms.modal.edit', compact('platform'));
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:50',
            'icon'        => 'nullable|string|max:255',
            'placeholder' => 'nullable|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
            'status'      => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $platform = ContactPlatform::findOrFail($id);
        $platform->name = $request->name;
        $platform->icon = $request->icon;
        $platform->placeholder = $request->placeholder;
        $platform->sort_order = $request->sort_order ?? 0;
        $platform->status = $request->status;
        $platform->save();

        if (!$request->ajax()) {
            return redirect()->route('contact_platforms.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $platform]);
        }
    }

    public function destroy($id)
    {
        $platform = ContactPlatform::findOrFail($id);
        $platform->delete();
        return response()->json(['result' => 'success', 'message' => _lang('Deleted Successfully')]);
    }
}
