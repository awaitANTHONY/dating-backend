<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInformation;
use App\Models\Interest;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FakeUserGeneratorController extends Controller
{
    public function index(Request $request)
    {
        return view('backend.users.fake_user_generator');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'count' => 'required|integer|min:1|max:1000',
            'password' => 'required|string|min:6',
            'gender' => 'required|in:male,female,other',
            'preference' => 'required|in:male,female,both',
            'interest_count' => 'required|integer|min:0',
            'language_count' => 'required|integer|min:0',
            'country_code' => 'nullable|string',
            'phone_length' => 'nullable|integer|min:4|max:15',
            'radius' => 'nullable|numeric|min:0',
        ]);

        $interests = Interest::pluck('id')->toArray();
        $languages = Language::pluck('id')->toArray();
        $created = 0;
        for ($i = 0; $i < $request->count; $i++) {

            $user = new User();
            $user->name = fake()->name();
            $user->email = fake()->unique()->email();
            $user->provider = 'email';
            $user->status = 1;
            $user->user_type = 'user';
            $user->password = bcrypt($request->password);
            $user->image = 'public/uploads/images/users/default.png';
            $user->save();

            $userInformation = new UserInformation();
            $userInformation->user_id = $user->id;
            $userInformation->bio = fake()->realText(120);
            $userInformation->gender = $request->gender;
            $userInformation->date_of_birth = now()->subYears(rand(18, 40))->format('Y-m-d');
            $userInformation->religion_id = null;
            $userInformation->latitude = null;
            $userInformation->longitude = null;
            $userInformation->search_radius = $request->radius ?? 10.0;
            $userInformation->country_code = $request->country_code;
            $userInformation->phone = $request->phone_length ? str_pad(rand(0, pow(10, $request->phone_length)-1), $request->phone_length, '0', STR_PAD_LEFT) : null;
            $userInformation->search_preference = json_encode([$request->preference]);
            $userInformation->relation_goals = json_encode([]);
            $userInformation->interests = json_encode(array_slice($interests, 0, $request->interest_count));
            $userInformation->languages = json_encode(array_slice($languages, 0, $request->language_count));
            $userInformation->wallet_balance = 0.00;
            $userInformation->images = json_encode([]);
            $userInformation->save();
            $created++;
        }
        return back()->with('success', $created . ' fake users generated successfully.');
    }
}
