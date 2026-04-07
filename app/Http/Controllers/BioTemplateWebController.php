<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BioTemplate;
use DataTables;
use Validator;

class BioTemplateWebController extends Controller
{
    public function index(Request $request)
    {
        $query = BioTemplate::orderBy('sort_order', 'ASC');

        if ($request->ajax()) {
            return DataTables::of($query)
                ->editColumn('gender', function ($template) {
                    return $template->gender ? ucfirst($template->gender) : _lang('All');
                })
                ->editColumn('is_active', function ($template) {
                    return ($template->is_active == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                })
                ->addColumn('action', function ($template) {
                    $action = '<div class="dropdown">';
                    $action .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                    $action .= _lang('Action');
                    $action .= '</button>';
                    $action .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('bio_templates.edit', $template->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit Bio Template') . '"><i class="fas fa-edit"></i> ' . _lang('Edit') . '</a>';
                    $action .= '<form action="' . route('bio_templates.destroy', $template->id) . '" method="post" class="ajax-delete">' . csrf_field() . method_field('DELETE') . '<button type="button" class="btn-remove dropdown-item"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button></form>';
                    $action .= '</div></div>';
                    return $action;
                })
                ->rawColumns(['action', 'is_active'])
                ->make(true);
        }

        return view('backend.bio_templates.index');
    }

    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.bio_templates.create');
        } else {
            return view('backend.bio_templates.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text'       => 'required|string|max:500',
            'gender'     => 'nullable|in:male,female',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $template = new BioTemplate();
        $template->text = $request->text;
        $template->gender = $request->gender;
        $template->sort_order = $request->sort_order ?? 0;
        $template->is_active = $request->is_active;
        $template->save();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $template]);
        }
    }

    public function edit(Request $request, $id)
    {
        $template = BioTemplate::findOrFail($id);

        if (!$request->ajax()) {
            return view('backend.bio_templates.edit', compact('template'));
        } else {
            return view('backend.bio_templates.modal.edit', compact('template'));
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'text'       => 'required|string|max:500',
            'gender'     => 'nullable|in:male,female',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $template = BioTemplate::findOrFail($id);
        $template->text = $request->text;
        $template->gender = $request->gender;
        $template->sort_order = $request->sort_order ?? 0;
        $template->is_active = $request->is_active;
        $template->save();

        if (!$request->ajax()) {
            return redirect()->route('bio_templates.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $template]);
        }
    }

    public function destroy($id)
    {
        $template = BioTemplate::findOrFail($id);
        $template->delete();
        return response()->json(['result' => 'success', 'message' => _lang('Deleted Successfully')]);
    }
}
