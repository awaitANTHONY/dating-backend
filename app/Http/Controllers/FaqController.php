<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Faq;
use DataTables;
use Validator;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $faqs = Faq::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($faqs)
                    ->editColumn('status', function($faq){
	                    return ($faq->status == 1) ? status(_lang('Active'), 'success') : status(_lang('In-Active'), 'danger');
	                })
                    ->editColumn('answer', function($faq){
	                    return \Illuminate\Support\Str::limit($faq->answer, 50);
	                })
                    ->addColumn('action', function($faq){
                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('faqs.edit', $faq->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('faqs.destroy', $faq->id) . '" method="post" class="ajax-delete">'
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
                    ->rawColumns(['action', 'status', 'answer'])
                    ->make(true);
        }

        return view('backend.faqs.index');
    }

    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.faqs.create');
        }else{
            return view('backend.faqs.modal.create');
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'question' => 'required|string|max:191',
           'answer' => 'required|string',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $faq = new Faq();
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->status = $request->status;
        $faq->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return back()->with('success', _lang('Information has been added sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Information has been added sucessfully.')]);
        }
    }

    public function show(Request $request, $id)
    {
        $faq = Faq::find($id);
        if(! $request->ajax()){
            return view('backend.faqs.show', compact('faq'));
        }else{
            return view('backend.faqs.modal.show', compact('faq'));
        } 
    }

    public function edit(Request $request,$id)
    {
        $faq = Faq::find($id);
        if(! $request->ajax()){
            return view('backend.faqs.edit', compact('faq'));
        }else{
            return view('backend.faqs.modal.edit', compact('faq'));
        }  
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
           'question' => 'required|string|max:191',
           'answer' => 'required|string',
           'status' => 'required|numeric|digits_between:0,11',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $faq = Faq::find($id);
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->status = $request->status;
        $faq->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return redirect('faqs')->with('success', _lang('Information has been updated sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Information has been updated sucessfully.')]);
        }
    }

    public function destroy(Request $request, $id)
    {
        $faq = Faq::find($id);
        $faq->delete();
        
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('faqs')->with('success', _lang('Information has been deleted sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully.')]);
        }
    }
}
