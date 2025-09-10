<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Language;
use DataTables;
use Validator;

class LanguageItemController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
    {
        $languages = Language::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($languages)
                    ->editColumn('status', function($language){
	                    return ($language->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
	                })
                    ->addColumn('image', function ($language) {
                        return '<img class="img-sm img-thumbnail" src="' . asset($language->image) . '">';
                    })
                    ->addColumn('action', function($language){

                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('languages.edit', $language->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('languages.destroy', $language->id) . '" method="post" class="ajax-delete">'
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
                    ->rawColumns(['action', 'status', 'image'])
                    ->make(true);
        }

        return view('backend.languages.index');
    }

    /**
    * Show the form for creating a new resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.languages.create');
        }else{
            return view('backend.languages.modal.create');
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
           'image' => 'required|image',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $language = new Language();
        
        $language->title = $request->title;
        $language->status = $request->status;

        if($request->hasFile('image')){
            $file = $request->file('image');
            $file_name = time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/uploads/images/languages/';
            $file->move(base_path($file_path), $file_name);
            $language->image = $file_path . $file_name;
        }

        $language->save();
        
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
        $language = Language::find($id);
        if(! $request->ajax()){
            return view('backend.languages.show', compact('language'));
        }else{
            return view('backend.languages.modal.show', compact('language'));
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
        $language = Language::find($id);
        if(! $request->ajax()){
            return view('backend.languages.edit', compact('language'));
        }else{
            return view('backend.languages.modal.edit', compact('language'));
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
           'image' => 'nullable|image',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $language = Language::find($id);
        
        $language->title = $request->title;
        $language->status = $request->status;

        if($request->hasFile('image')){
            $file = $request->file('image');
            $file_name = time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/uploads/images/languages/';
            $file->move(base_path($file_path), $file_name);
            $language->image = $file_path . $file_name;
        }

        $language->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return redirect('languages')->with('success', _lang('Information has been updated sucessfully.'));
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
        $language = Language::find($id);
        $language->delete();
        
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('languages')->with('success', _lang('Information has been deleted sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully.')]);
        }
    }
}
