<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MoodSuggestion;
use DataTables;
use Validator;

class MoodSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $query = MoodSuggestion::orderBy('sort_order', 'ASC');

        if ($request->ajax()) {
            return DataTables::of($query)
                ->editColumn('is_active', function ($mood) {
                    return ($mood->is_active == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                })
                ->addColumn('action', function ($mood) {
                    $action = '<div class="dropdown">';
                    $action .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                    $action .= _lang('Action');
                    $action .= '</button>';
                    $action .= '<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                    $action .= '<a href="' . route('mood_suggestions.edit', $mood->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit Mood Suggestion') . '"><i class="fas fa-edit"></i> ' . _lang('Edit') . '</a>';
                    $action .= '<form action="' . route('mood_suggestions.destroy', $mood->id) . '" method="post" class="ajax-delete">' . csrf_field() . method_field('DELETE') . '<button type="button" class="btn-remove dropdown-item"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button></form>';
                    $action .= '</div></div>';
                    return $action;
                })
                ->rawColumns(['action', 'is_active'])
                ->make(true);
        }

        return view('backend.mood_suggestions.index');
    }

    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.mood_suggestions.create');
        } else {
            return view('backend.mood_suggestions.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text'       => 'required|string|max:50',
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

        $mood = new MoodSuggestion();
        $mood->text = $request->text;
        $mood->sort_order = $request->sort_order ?? 0;
        $mood->is_active = $request->is_active;
        $mood->save();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $mood]);
        }
    }

    public function edit(Request $request, $id)
    {
        $mood = MoodSuggestion::findOrFail($id);

        if (!$request->ajax()) {
            return view('backend.mood_suggestions.edit', compact('mood'));
        } else {
            return view('backend.mood_suggestions.modal.edit', compact('mood'));
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'text'       => 'required|string|max:50',
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

        $mood = MoodSuggestion::findOrFail($id);
        $mood->text = $request->text;
        $mood->sort_order = $request->sort_order ?? 0;
        $mood->is_active = $request->is_active;
        $mood->save();

        if (!$request->ajax()) {
            return redirect()->route('mood_suggestions.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $mood]);
        }
    }

    public function destroy($id)
    {
        $mood = MoodSuggestion::findOrFail($id);
        $mood->delete();
        return response()->json(['result' => 'success', 'message' => _lang('Deleted Successfully')]);
    }
}
