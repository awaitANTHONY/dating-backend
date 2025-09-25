<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\RelationshipStatus;
use DataTables;
use Validator;

class RelationshipStatusController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        $relationship_statuses = RelationshipStatus::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($relationship_statuses)
                    ->editColumn('status', function($relationship_status){
                        return ($relationship_status->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
                    })
                    ->addColumn('action', function($relationship_status){

                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('relationship-statuses.edit', $relationship_status->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('relationship-statuses.destroy', $relationship_status->id) . '" method="post" class="ajax-delete">'
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
                    ->rawColumns(['action', 'status'])
                    ->make(true);
        }

        return view('backend.relationship_statuses.index');
    }


    /**
    * Show the form for creating a new resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.relationship_statuses.create');
        }else{
            return view('backend.relationship_statuses.modal.create');
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
            
           'title' => 'required|string|max:191',
           'status' => 'required|numeric|digits_between:0,11',

        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $relationship_status = new RelationshipStatus();
        
        $relationship_status->title = $request->title;
        $relationship_status->status = $request->status;

        $relationship_status->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return back()->with('success', _lang('Information has been added sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Information has been added sucessfully.')]);
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
        $relationship_status = RelationshipStatus::find($id);
        if(! $request->ajax()){
            return view('backend.relationship_statuses.show', compact('relationship_status'));
        }else{
            return view('backend.relationship_statuses.modal.show', compact('relationship_status'));
        } 
    }

    /**
    * Show the form for editing the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function edit(Request $request,$id)
    {
        $relationship_status = RelationshipStatus::find($id);
        if(! $request->ajax()){
            return view('backend.relationship_statuses.edit', compact('relationship_status'));
        }else{
            return view('backend.relationship_statuses.modal.edit', compact('relationship_status'));
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
            
           'title' => 'required|string|max:191',
           'status' => 'required|numeric|digits_between:0,11',

        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $relationship_status = RelationshipStatus::find($id);
        
        $relationship_status->title = $request->title;
        $relationship_status->status = $request->status;

        $relationship_status->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return redirect('relationship-statuses')->with('success', _lang('Information has been updated sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Information has been updated sucessfully.')]);
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
        $relationship_status = RelationshipStatus::find($id);
        $relationship_status->delete();
        
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('relationship-statuses')->with('success', _lang('Information has been deleted sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully.')]);
        }
    }
}
