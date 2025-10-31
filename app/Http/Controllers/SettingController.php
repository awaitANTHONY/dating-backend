<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Carbon\Carbon;
use Image;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function general(Request $request)
    {
        return view('backend.settings.general');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function app(Request $request)
    {
        return view('backend.settings.app');
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function prediction_app(Request $request)
    {
        return view('backend.settings.prediction_app');
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function real_app(Request $request)
    {
        return view('backend.settings.real_app');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store_settings(Request $request)
    {
        foreach($request->except('_token') as $key => $value){
            if($key != '' && $value != ''){
                $data = array();
                $data['value'] = is_array($value) ? json_encode($value) : xss_clean($value); 
                $data['updated_at'] = now();
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    
                    if(str_contains($key, '_hidden')){
                        $key = str_replace('_hidden', '', $key);
                        $name = $key . '.' .$file->getClientOriginalExtension();
                        //save in storage
                        $path = storage_path('app/private/files/');
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($path)) {
                            mkdir($path, 0755, true);
                        }
                        
                        $file->move($path, $name);
                        $data['value'] = 'app/private/files/' . $name; 
                        $data['updated_at'] = now();
                    }else{
                        $name = $key . '.' .$file->getClientOriginalExtension();
                        $path = public_path('uploads/files/');
                        $file->move($path, $name);
                        $data['value'] = 'public/uploads/files/' . $name; 
                        $data['updated_at'] = now();
                    }
                }

                
                if(Setting::where('name', $key)->exists()){                
                    Setting::where('name','=', $key)->update($data);         
                }else{
                    $data['name'] = $key; 
                    $data['created_at'] = now();
                    Setting::insert($data); 
                }
            }
        }
        
        cache()->flush();
        
        if(! $request->ajax()){
            return back()->with('success', _lang('Information update sucessfully.'));
        }else{
            return response()->json(['result' => 'success', 'message' => _lang('Information update sucessfully.')]);
        }
    }
}
