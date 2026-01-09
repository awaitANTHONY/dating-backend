<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\AccountDeleteRequest;
use DataTables;
use Validator;

class AccountDeleteRequestController extends Controller
{
    /**
     * Display a listing of the account delete requests (Backend Admin)
     */
    public function index(Request $request)
    {
        $requests = AccountDeleteRequest::orderBy('id', 'DESC');

        if ($request->ajax()) {
            return DataTables::of($requests)
                    ->editColumn('type', function($deleteRequest){
                        return ($deleteRequest->type == 1) 
                            ? '<span class="badge badge-info">' . _lang('Clear Data') . '</span>' 
                            : '<span class="badge badge-warning">' . _lang('Clear Data & Account') . '</span>';
                    })
                    ->editColumn('accepted', function($deleteRequest){
	                    return ($deleteRequest->accepted == 1) 
                            ? status(_lang('Accepted'), 'success') 
                            : status(_lang('Pending'), 'warning');
	                })
                    ->editColumn('created_at', function($deleteRequest){
	                    return $deleteRequest->created_at->format('Y-m-d H:i');
	                })
                    ->addColumn('action', function($deleteRequest){
                        $action = '<div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            ' . _lang('Action') . '
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">';
                        $action .= '<a href="' . route('account-delete-requests.edit', $deleteRequest->id) . '" class="dropdown-item ajax-modal" data-title="' . _lang('Edit') . '">
                                        <i class="fas fa-edit"></i>
                                        ' . _lang('Edit') . '
                                    </a>';
                        $action .= '<form action="' . route('account-delete-requests.destroy', $deleteRequest->id) . '" method="post" class="ajax-delete">'
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
                    ->rawColumns(['action', 'type', 'accepted'])
                    ->make(true);
        }

        return view('backend.account_delete_requests.index');
    }

    /**
     * Show the public form for creating a new account delete request
     */
    public function publicForm()
    {
        return view('public.account_delete_request');
    }

    /**
     * Store a newly created account delete request from public form
     */
    public function publicStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'email' => 'required|email|max:255',
           'type' => 'required|in:1,2',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $deleteRequest = new AccountDeleteRequest();
        $deleteRequest->email = $request->email;
        $deleteRequest->type = $request->type;
        $deleteRequest->accepted = 0;
        $deleteRequest->save();

        return back()->with('success', _lang('Your account deletion request has been submitted successfully. We will process it shortly.'));
    }

    /**
     * Show the form for creating a new resource (Backend Admin)
     */
    public function create(Request $request)
    {
        if( ! $request->ajax()){
            return view('backend.account_delete_requests.create');
        }else{
            return view('backend.account_delete_requests.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage (Backend Admin)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'email' => 'required|email|max:255',
           'type' => 'required|in:1,2',
           'accepted' => 'required|numeric|digits_between:0,1',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $deleteRequest = new AccountDeleteRequest();
        $deleteRequest->email = $request->email;
        $deleteRequest->type = $request->type;
        $deleteRequest->accepted = $request->accepted;
        $deleteRequest->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return back()->with('success', _lang('Request has been added successfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Request has been added successfully.')]);
        }
    }

    /**
     * Display the specified resource
     */
    public function show(Request $request, $id)
    {
        $deleteRequest = AccountDeleteRequest::find($id);
        if(! $request->ajax()){
            return view('backend.account_delete_requests.show', compact('deleteRequest'));
        }else{
            return view('backend.account_delete_requests.modal.show', compact('deleteRequest'));
        } 
    }

    /**
     * Show the form for editing the specified resource
     */
    public function edit(Request $request, $id)
    {
        $deleteRequest = AccountDeleteRequest::find($id);
        if(! $request->ajax()){
            return view('backend.account_delete_requests.edit', compact('deleteRequest'));
        }else{
            return view('backend.account_delete_requests.modal.edit', compact('deleteRequest'));
        }  
    }

    /**
     * Update the specified resource in storage
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
           'email' => 'required|email|max:255',
           'type' => 'required|in:1,2',
           'accepted' => 'required|numeric|digits_between:0,1',
        ]);

        if ($validator->fails()) {
            if($request->ajax()){ 
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            }else{
                return back()->withErrors($validator)->withInput();
            }			
        }

        $deleteRequest = AccountDeleteRequest::find($id);
        $deleteRequest->email = $request->email;
        $deleteRequest->type = $request->type;
        $deleteRequest->accepted = $request->accepted;
        $deleteRequest->save();
        
        cache()->flush();

        if(! $request->ajax()){
            return redirect('account-delete-requests')->with('success', _lang('Request has been updated successfully.'));
        }else{
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Request has been updated successfully.')]);
        }
    }

    /**
     * Remove the specified resource from storage
     */
    public function destroy(Request $request, $id)
    {
        $deleteRequest = AccountDeleteRequest::find($id);
        $deleteRequest->delete();
        
        cache()->flush();
        
        if(! $request->ajax()){
            return redirect('account-delete-requests')->with('success', _lang('Request has been deleted successfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Request has been deleted successfully.')]);
        }
    }
}
