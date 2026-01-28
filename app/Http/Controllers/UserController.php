<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInformation;
use App\Models\Religion;
use App\Models\RelationGoal;
use App\Models\Interest;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DataTables;
use Image;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $search_by = $request->search_by;
            $search = $request->search;
            
            $users = User::select(['id', 'name', 'email', 'image', 'status'])
                        ->where('user_type', 'user');
            
            if (!empty($search_by) && !empty($search)) {
                $users->where($search_by, 'LIKE', '%' . $search . '%');
            }
            
            return DataTables::of($users)
                ->editColumn('image', function ($user) {
                    return '<img class="img-sm img-thumbnail" src="' . asset($user->image) . '">';
                })
                ->editColumn('status', function ($user) {
                    if ($user->status == 1) {
                        return status(_lang('Active'), 'success');
                    } elseif ($user->status == 4) {
                        return status(_lang('Banned'), 'warning');
                    } else {
                        return status(_lang('In-Active'), 'danger');
                    }
                })
                ->addColumn('action', function($user){
                    return '<div class="dropdown">
                                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action') . '</button>
                                <div class="dropdown-menu">
                                    <a href="' . route('users.show', $user->id) . '" class="dropdown-item"><i class="fas fa-eye"></i> ' . _lang('Details') . '</a>
                                    <a href="' . route('users.edit', $user->id) . '" class="dropdown-item"><i class="fas fa-edit"></i> ' . _lang('Edit') . '</a>
                                    ' . ($user->status == 4 
                                        ? '<a href="' . route('users.unban', $user->id) . '" class="dropdown-item ajax-unban"><i class="fas fa-unlock"></i> ' . _lang('Unban User') . '</a>'
                                        : '<a href="' . route('users.ban', $user->id) . '" class="dropdown-item ajax-ban"><i class="fas fa-ban"></i> ' . _lang('Ban User') . '</a>'
                                    ) . '
                                    <form action="' . route('users.destroy', $user->id) . '" method="post" class="ajax-delete">' . csrf_field() . method_field('DELETE') . '
                                        <button type="button" class="btn-remove dropdown-item"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button>
                                    </form>
                                </div>
                            </div>';
                })
                ->rawColumns(['action', 'status', 'image'])
                ->make(true);
        }

        return view('backend.users.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.users.create');
        } else {
            return view('backend.users.modal.create');
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
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'email' => 'nullable|string|email|max:191|unique:users',
            'status' => 'required',
            'password' => 'required|string|min:6',
            'bio' => 'nullable|string|max:1000',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'religion_id' => 'nullable|exists:religions,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'search_preference' => 'required',
            'relation_goals' => 'nullable|array',
            'relation_goals.*' => 'exists:relation_goals,id',
            'interests' => 'nullable|array',
            'interests.*' => 'exists:interests,id',
            'languages' => 'nullable|array',
            'languages.*' => 'exists:languages,id',
            'image' => 'nullable|image',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image',
            'is_zodiac_sign_matter' => 'nullable|boolean',
            'is_food_preference_matter' => 'nullable|boolean',
            'age' => 'nullable|integer|min:18|max:100',
            'relationship_status_id' => 'nullable|exists:relationship_statuses,id',
            'ethnicity_id' => 'nullable|exists:ethnicities,id',
            'alkohol' => 'nullable|in:dont_drink,drink_frequently,drink_socially,prefer_not_to_say',
            'smoke' => 'nullable|in:dont_smoke,smoke_regularly,smoke_occasionally,prefer_not_to_say',
            'education_id' => 'nullable|exists:educations,id',
            'preffered_age' => 'nullable|string',
            'height' => 'nullable|integer|min:100|max:300',
            'carrer_field_id' => 'nullable|exists:career_fields,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }
        \DB::beginTransaction();

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->provider = 'email';
        $user->status = $request->status;
        $user->user_type = 'user';
        $user->password = bcrypt($request->password);

        // Handle profile image (main image) using move() for consistency
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $ImageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imgPath = 'public/uploads/images/users/';
            $image->move(base_path($imgPath), $ImageName);
            $user->image = $imgPath . $ImageName;
        }

        $user->save();

        // Handle other images (multiple) using move() like single image upload
        $otherImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imgFile) {
                $imgName = time() . '_' . uniqid() . '.' . $imgFile->getClientOriginalExtension();
                $imgPath = "public/uploads/images/users/{$user->id}/";
                $imgFile->move(base_path($imgPath), $imgName);
                $otherImages[] = $imgPath . $imgName;
            }
        }

        // Create UserInformation
        $userInformation = new UserInformation();
        $userInformation->user_id = $user->id;
        $userInformation->bio = $request->bio;
        $userInformation->gender = $request->gender;
        $userInformation->date_of_birth = $request->date_of_birth;
        $userInformation->religion_id = $request->religion_id;
        $userInformation->latitude = $request->latitude;
        $userInformation->longitude = $request->longitude;
        $userInformation->search_radius = $request->search_radius ?? 1.0;
        $userInformation->country_code = $request->country_code;
        $userInformation->phone = $request->phone;
        $userInformation->search_preference = $request->search_preference;
        $userInformation->relation_goals = json_encode(is_array($request->relation_goals) ? $request->relation_goals : (empty($request->relation_goals) ? [] : explode(',', $request->relation_goals)));
        $userInformation->interests = json_encode(is_array($request->interests) ? $request->interests : (empty($request->interests) ? [] : explode(',', $request->interests)));
        $userInformation->languages = json_encode(is_array($request->languages) ? $request->languages : (empty($request->languages) ? [] : explode(',', $request->languages)));
        $userInformation->images = json_encode($otherImages);
        $userInformation->is_zodiac_sign_matter = $request->is_zodiac_sign_matter ?? false;
        $userInformation->is_food_preference_matter = $request->is_food_preference_matter ?? false;
        $userInformation->age = $request->age;
        $userInformation->relationship_status_id = $request->relationship_status_id;
        $userInformation->ethnicity_id = $request->ethnicity_id;
        $userInformation->alkohol = $request->alkohol;
        $userInformation->smoke = $request->smoke;
        $userInformation->education_id = $request->education_id;
        $userInformation->preffered_age = $request->preffered_age;
        $userInformation->height = $request->height;
        $userInformation->carrer_field_id = $request->carrer_field_id;
        $userInformation->save();

        \DB::commit();

        if (!$request->ajax()) {
            return redirect('users')->with('success', _lang('Information has been added.'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => 'users', 'message' => _lang('Information has been added sucessfully.')]);
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
        $user = User::find($id);

        if (!$request->ajax()) {
            return view('backend.users.show', compact('user'));
        } else {
            return view('backend.users.modal.show', compact('user'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $user = User::find($id);

        if (!$request->ajax()) {
            return view('backend.users.edit', compact('user'));
        } else {
            return view('backend.users.modal.edit', compact('user'));
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
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'email' => [
                'nullable',
                Rule::unique('users')->ignore($id),
            ],
            'status' => 'required',
            'bio' => 'nullable|string|max:1000',
            'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'religion_id' => 'nullable|exists:religions,id',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'search_preference' => 'required',
            'relation_goals' => 'nullable|array',
            'relation_goals.*' => 'exists:relation_goals,id',
            'interests' => 'nullable|array',
            'interests.*' => 'exists:interests,id',
            'languages' => 'nullable|array',
            'languages.*' => 'exists:languages,id',
            'image' => 'nullable|image',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image',
            'is_zodiac_sign_matter' => 'nullable|boolean',
            'is_food_preference_matter' => 'nullable|boolean',
            'age' => 'nullable|integer|min:18|max:100',
            'relationship_status_id' => 'nullable|exists:relationship_statuses,id',
            'ethnicity_id' => 'nullable|exists:ethnicities,id',
            'alkohol' => 'nullable|in:dont_drink,drink_frequently,drink_socially,prefer_not_to_say',
            'smoke' => 'nullable|in:dont_smoke,smoke_regularly,smoke_occasionally,prefer_not_to_say',
            'education_id' => 'nullable|exists:educations,id',
            'preffered_age' => 'nullable|string',
            'height' => 'nullable|integer|min:100|max:300',
            'carrer_field_id' => 'nullable|exists:career_fields,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        \DB::beginTransaction();
        try {
            $user = User::find($id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->status = $request->status;

            // Handle profile image (main image)
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $ImageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imgPath = 'public/uploads/images/users/';
                $image->move(base_path($imgPath), $ImageName);
                $user->image = $imgPath . $ImageName;
            }

            $user->save();

            // Handle other images (multiple)
            $otherImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imgFile) {
                    $imgName = time() . '_' . uniqid() . '.' . $imgFile->getClientOriginalExtension();
                    $imgPath = "public/uploads/images/users/{$user->id}/";
                    $imgFile->move(base_path($imgPath), $imgName);
                    $otherImages[] = $imgPath . $imgName;
                }
            }

            // Update or create UserInformation
            $userInformation = UserInformation::firstOrNew(['user_id' => $user->id]);
            $userInformation->bio = $request->bio;
            $userInformation->gender = $request->gender;
            $userInformation->date_of_birth = $request->date_of_birth;
            $userInformation->religion_id = $request->religion_id;
            $userInformation->latitude = $request->latitude;
            $userInformation->longitude = $request->longitude;
            $userInformation->search_radius = $request->search_radius ?? 100.0;
            $userInformation->country_code = $request->country_code;
            $userInformation->phone = $request->phone;
            $userInformation->search_preference = $request->search_preference;
            $userInformation->relation_goals = json_encode(is_array($request->relation_goals) ? $request->relation_goals : (empty($request->relation_goals) ? [] : explode(',', $request->relation_goals)));
            $userInformation->interests = json_encode(is_array($request->interests) ? $request->interests : (empty($request->interests) ? [] : explode(',', $request->interests)));
            $userInformation->languages = json_encode(is_array($request->languages) ? $request->languages : (empty($request->languages) ? [] : explode(',', $request->languages)));
            if (!empty($otherImages)) {
                $userInformation->images = json_encode($otherImages);
            }
            $userInformation->is_zodiac_sign_matter = $request->is_zodiac_sign_matter ?? false;
            $userInformation->is_food_preference_matter = $request->is_food_preference_matter ?? false;
            $userInformation->age = $request->age;
            $userInformation->relationship_status_id = $request->relationship_status_id;
            $userInformation->ethnicity_id = $request->ethnicity_id;
            $userInformation->alkohol = $request->alkohol;
            $userInformation->smoke = $request->smoke;
            $userInformation->education_id = $request->education_id;
            $userInformation->preffered_age = $request->preffered_age;
            $userInformation->height = $request->height;
            $userInformation->carrer_field_id = $request->carrer_field_id;
            $userInformation->save();

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [$e->getMessage()]]);
            } else {
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        }

        if (!$request->ajax()) {
            return redirect('users')->with('success', _lang('Information has been updated.'));
        } else {
            return response()->json(['result' => 'success', 'redirect' => 'users', 'message' => _lang('Information has been updated sucessfully.')]);
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
        $user = User::find($id);
        $userInformation = UserInformation::where('user_id', $user->id)->first();
        if ($userInformation) {
            $userInformation->delete();
        }
        $user->delete();

        if (!$request->ajax()) {
            return back()->with('success', _lang('Information has been deleted'));
        } else {
            return response()->json(['result' => 'success', 'message' => _lang('Information has been deleted sucessfully')]);
        }
    }

    /**
     * Show the wallet management page for a user.
     */
    public function wallet_manage($id)
    {
        $user = User::findOrFail($id);
        $walletBalance = $user->wallet_balance ?? 0;
        $walletLogs = \App\Models\WalletTransaction::where('user_id', $user->id)->orderByDesc('id')->get();
        return view('backend.users.wallet_manage', compact('user', 'walletBalance', 'walletLogs'));
    }

    /**
     * Handle add or subtract wallet balance for a user.
     */
    public function update_wallet(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:Credit,Debit',
            'amount' => 'required|numeric|min:1',
        ]);

        $user = User::findOrFail($id);

        $amount = $request->amount;
        $type = $request->type;

        // Calculate current balance
        $currentBalance = $user->wallet_balance ?? 0;

        if ($type === 'Debit' && $amount > $currentBalance) {
            return back()->with('error', 'Insufficient wallet balance!');
        }

        $message = $type === 'Credit' ? 'Wallet credited successfully!' : 'Wallet debited successfully!';

        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => $type,
        ]);

        $user->wallet_balance = $type === 'Credit' ? $currentBalance + $amount : $currentBalance - $amount;
        $user->save();

        if (!$request->ajax()) {
            return back()->with('success', $message);
        } else {
            return response()->json(['result' => 'success', 'message' => $message]);
        }
    }

    /**
     * Show the coin management page for a user.
     */
    public function coin_manage($id)
    {
        $user = User::findOrFail($id);
        $coinBalance = $user->coin_balance ?? 0;
        $coinLogs = \App\Models\CoinTransaction::where('user_id', $user->id)->orderByDesc('id')->get();
        return view('backend.users.coin_manage', compact('user', 'coinBalance', 'coinLogs'));
    }

    /**
     * Handle add or subtract coin balance for a user.
     */
    public function update_coin(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:Credit,Debit',
            'amount' => 'required|numeric|min:1',
        ]);

        $user = User::findOrFail($id);

        $amount = $request->amount;
        $type = $request->type;

        // Calculate current balance
        $currentBalance = $user->coin_balance ?? 0;

        if ($type === 'Debit' && $amount > $currentBalance) {
            return back()->with('error', 'Insufficient coin balance!');
        }

        $message = $type === 'Credit' ? 'Coin added successfully!' : 'Coin subtracted successfully!';

        \App\Models\CoinTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => $type,
        ]);

 
        $user->coin_balance = $type === 'Credit' ? $currentBalance + $amount : $currentBalance - $amount;
        $user->save();

        if (!$request->ajax()) {
            return back()->with('success', $message);
        } else {
            return response()->json(['result' => 'success', 'message' => $message]);
        }
    }

    /**
     * Ban a user by setting status to 4.
     */
    public function ban_user(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->status = 4; // Banned status
        $user->save();

        $message = 'User has been banned successfully!';

        if (!$request->ajax()) {
            return back()->with('success', $message);
        } else {
            return response()->json(['result' => 'success', 'message' => $message]);
        }
    }

    /**
     * Unban a user by setting status to 1.
     */
    public function unban_user(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->status = 1; // Active status
        $user->save();

        $message = 'User has been unbanned successfully!';

        if (!$request->ajax()) {
            return back()->with('success', $message);
        } else {
            return response()->json(['result' => 'success', 'message' => $message]);
        }
    }
}
