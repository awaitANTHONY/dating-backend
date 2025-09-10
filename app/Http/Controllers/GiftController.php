<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Gift;
use DataTables;
use Validator;

class GiftController extends Controller
{
    public function index(Request $request)
    {
        $gifts = Gift::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($gifts)
                    ->editColumn('status', function($gift){
	                    return ($gift->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
	                })
                    ->addColumn('image', function ($gift) {
                        if($gift->image) {
                            return '<img class="img-sm img-thumbnail" src="' . asset('uploads/images/gifts/' . $gift->image) . '">';
                        }
                        return 'No Image';
                    })
                    ->addColumn('action', function($gift){
                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('gifts.edit', $gift->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('gifts.destroy', $gift->id) . '" method="post" class="ajax-delete">'
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
                    ->rawColumns(['action', 'status', 'image'])
                    ->make(true);
        }

        return view('backend.gifts.index');
    }

    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.gifts.create');
        }else{
            return view('backend.gifts.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'coin' => 'required|numeric',
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

        $gift = new Gift();
        $gift->coin = $request->coin;
        $gift->status = $request->status;

        if($request->hasFile('image')){
            $file = $request->file('image');
            $file_name = time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/uploads/images/gifts/';
            $file->move(base_path($file_path), $file_name);
            $gift->image = $file_path . $file_name;
        }

        $gift->save();
        cache()->flush();

        if(! $request->ajax()){
            return back()->with('success', _lang('Information has been added sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Information has been added sucessfully.')]);
        }
    }

    public function show(Request $request, $id)
    {
        $gift = Gift::find($id);
        if(! $request->ajax()){
            return view('backend.gifts.show', compact('gift'));
        }else{
            return view('backend.gifts.modal.show', compact('gift'));
        } 
    }

    public function edit(Request $request,$id)
    {
        $gift = Gift::find($id);
        if(! $request->ajax()){
            return view('backend.gifts.edit', compact('gift'));
        }else{
            return view('backend.gifts.modal.edit', compact('gift'));
        }  
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
           'coin' => 'required|numeric',
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

        $gift = Gift::find($id);
        $gift->coin = $request->coin;
        $gift->status = $request->status;

        if($request->hasFile('image')){
            $file = $request->file('image');
            $file_name = time() . '.' . $file->getClientOriginalExtension();
            $file_path = 'public/uploads/images/gifts/';
            $file->move(base_path($file_path), $file_name);
            $gift->image = $file_path . $file_name;
        }

        $gift->save();
        cache()->flush();

        if(! $request->ajax()){
            return redirect('gifts')->with('success', _lang('Information has been updated sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Information has been updated sucessfully.')]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $gift = Gift::find($id);
        $gift->delete();
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('gifts')->with('success', _lang('Information has been deleted sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully.')]);
        }
    }
}
