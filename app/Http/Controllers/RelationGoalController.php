<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\RelationGoal;
use DataTables;
use Validator;

class RelationGoalController extends Controller
{
    public function index(Request $request)
    {
        $relationGoals = RelationGoal::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($relationGoals)
                    ->editColumn('status', function($relationGoal){
	                    return ($relationGoal->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
	                })
                    ->addColumn('action', function($relationGoal){
                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('relation_goals.edit', $relationGoal->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('relation_goals.destroy', $relationGoal->id) . '" method="post" class="ajax-delete">'
                                    . csrf_field() 
                                    . method_field('DELETE') 
                                    . '<button type="button" class="btn-remove dropdown-item">
                                            <i class="fas fa-trash-alt"></i>
                                            ' . _lang('Delete') . '
                                        </button>
                                    </form>';
                        $action .= '</div></div>';
                        return $action;
                    })
                    ->rawColumns(['action', 'status'])
                    ->make(true);
        }

        return view('backend.relation_goals.index');
    }

    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.relation_goals.create');
        }else{
            return view('backend.relation_goals.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'title' => 'required|string|max:191',
           'subtitle' => 'required|string|max:191',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $relationGoal = new RelationGoal();
        $relationGoal->title = $request->title;
        $relationGoal->subtitle = $request->subtitle;
        $relationGoal->status = $request->status;
        $relationGoal->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return back()->with('success', _lang('Information has been added sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Information has been added sucessfully.')]);
        }
    }

    public function show(Request $request, $id)
    {
        $relationGoal = RelationGoal::find($id);
        if(! $request->ajax()){
            return view('backend.relation_goals.show', compact('relationGoal'));
        }else{
            return view('backend.relation_goals.modal.show', compact('relationGoal'));
        } 
    }

    public function edit(Request $request,$id)
    {
        $relationGoal = RelationGoal::find($id);
        if(! $request->ajax()){
            return view('backend.relation_goals.edit', compact('relationGoal'));
        }else{
            return view('backend.relation_goals.modal.edit', compact('relationGoal'));
        }  
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
           'title' => 'required|string|max:191',
           'subtitle' => 'required|string|max:191',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $relationGoal = RelationGoal::find($id);
        $relationGoal->title = $request->title;
        $relationGoal->subtitle = $request->subtitle;
        $relationGoal->status = $request->status;
        $relationGoal->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return redirect('relation_goals')->with('success', _lang('Information has been updated sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Information has been updated sucessfully.')]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $relationGoal = RelationGoal::find($id);
        $relationGoal->delete();
        
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('relation_goals')->with('success', _lang('Information has been deleted sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully.')]);
        }
    }
}
