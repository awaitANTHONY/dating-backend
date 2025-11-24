<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInformation;
use App\Models\Interest;
use App\Models\Language;
use App\Models\Religion;
use App\Models\RelationGoal;
use App\Models\RelationshipStatus;
use App\Models\Ethnicity;
use App\Models\Education;
use App\Models\CareerField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

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

        \DB::beginTransaction();

        $interests = Interest::pluck('id')->toArray();
        $languages = Language::pluck('id')->toArray();
        $religion = Religion::pluck('id')->toArray();
        $relation_goals = RelationGoal::pluck('id')->toArray();
        $relationship_status = RelationshipStatus::pluck('id')->toArray();
        $ethnicities = Ethnicity::pluck('id')->toArray();
        $educations = Education::pluck('id')->toArray();
        $career_fields = CareerField::pluck('id')->toArray();
        $created = 0;

        
        for ($i = 0; $i < $request->count; $i++) {

            $faker = Faker::create();

            $genderFolder = $request->gender == 'male' ? 'male' : 'female';
            $imageDir = public_path('uploads/images/users/' . $genderFolder);
            $imageFiles = [];
            if (is_dir($imageDir)) {
                $imageFiles = array_values(array_filter(scandir($imageDir), function($file) use ($imageDir) {
                    return is_file($imageDir . DIRECTORY_SEPARATOR . $file) && !in_array($file, ['.', '..']);
                }));
            }
            if (!empty($imageFiles)) {
                $randomImage = $imageFiles[array_rand($imageFiles)];
                $image = 'public/uploads/images/users/' . $genderFolder . '/' . $randomImage;
            } else {
                $image = 'public/uploads/images/users/default.png';
            }

            $user = new User();
            $user->name = $faker->name($request->gender);
            $user->email = $faker->unique()->email();
            $user->provider = 'email';
            $user->status = 1;
            $user->user_type = 'user';
            $user->password = bcrypt($request->password);
            $user->image = $image;
            $user->is_fake = 1;
            $user->coin_balance = 0;
            $user->wallet_balance = 0.00;
            $user->save();

            $userInformation = new UserInformation();
            $userInformation->user_id = $user->id;
            $userInformation->bio = $faker->realText(120);
            $userInformation->gender = $request->gender;
            $userInformation->date_of_birth = now()->subYears(rand(18, 40))->format('Y-m-d');
            $userInformation->religion_id = $religion ? $religion[array_rand($religion)] : null;
            
            // Set latitude and longitude based on country code
            if ($request->country_code) {
                $coordinates = getCountryCoordinates($request->country_code);
                $userInformation->latitude = $coordinates['lat'];
                $userInformation->longitude = $coordinates['lng'];
            } else {
                // Default coordinates if no country code provided
                $userInformation->latitude = $faker->latitude(20.6, 26.6);
                $userInformation->longitude = $faker->longitude(88.0, 92.7);
            }

            $userInformation->address = $faker->address;
            
            $userInformation->search_radius = $request->radius ?? 10.0;
            $userInformation->country_code = $request->country_code;
            $userInformation->phone = $request->phone_length ? str_pad(rand(0, pow(10, $request->phone_length)-1), $request->phone_length, '0', STR_PAD_LEFT) : null;
            $userInformation->search_preference = $request->preference;
            $userInformation->relation_goals = json_encode([$relation_goals ? $relation_goals[array_rand($relation_goals)] : null]);
            $userInformation->interests = json_encode(array_slice($interests, 0, $request->interest_count));
            $userInformation->languages = json_encode(array_slice($languages, 0, $request->language_count));
            $userInformation->is_zodiac_sign_matter = (bool)rand(0,1);
            $userInformation->is_food_preference_matter = (bool)rand(0,1);
            $userInformation->age = \Illuminate\Support\Carbon::parse($userInformation->date_of_birth)->diffInYears(now());
            $userInformation->relationship_status_id = $relationship_status ? $relationship_status[array_rand($relationship_status)] : null;
            $userInformation->ethnicity_id = $ethnicities ? $ethnicities[array_rand($ethnicities)] : null;
            $userInformation->carrer_field_id = $career_fields ? $career_fields[array_rand($career_fields)] : null;
            $userInformation->alkohol = $faker->randomElement(['dont_drink', 'drink_frequently', 'drink_socially', 'prefer_not_to_say']);
            $userInformation->smoke = $faker->randomElement(['dont_smoke', 'smoke_regularly', 'smoke_occasionally', 'prefer_not_to_say']);
            $userInformation->education_id = $educations ? $educations[array_rand($educations)] : null; 
            $userInformation->images = json_encode([]);
            $userInformation->save();
            $created++;

        }

        \DB::commit();
        return back()->with('success', $created . ' fake users generated successfully.');
    }

    
}
