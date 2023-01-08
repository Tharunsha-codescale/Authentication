<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    //
    public function signup(Request $req)
    {
        //valdiate
        $rules = [
            'name' => 'required|string',
            'email' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
        ];
        $validator = Validator::make($req->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        //create new user in users table
        $user = User::create([
            'name' => $req->name,
            'email' => $req->email,
            'password' => Hash::make($req->password),
        ]);
        $token = $user->createToken('Personal Access Token')->plainTextToken;
        $response = ['user' => $user, 'token' => $token];
        return response()->json($response, 200);
    }

    public function login(Request $req)
    {
        // validate inputs
        $rules = [
            'email' => 'required',
            'password' => 'required|string'
        ];
        $req->validate($rules);
        // find user email in users table
        $user = User::where('email', $req->email)->first();
        // if user email found and password is correct
        if ($user && Hash::check($req->password, $user->password)) {
            $token = $user->createToken('Personal Access Token')->plainTextToken;
            $response = ['user' => $user, 'token' => $token];
            return response()->json($response, 200);
        }
        $response = ['message' => 'Incorrect email or password'];
        return response()->json($response, 400);
    }

    //forgetpassword api method

    public function forgetPassword(Request $req)
    {
        try{

          $user = User::where('email',$req->email)->get();

          if(count($user) > 0){

            $token = Str::random(40);
            $domain = URL::to('/');
            $url = $domain.'/reset-password?token='.$token;

            $data['url'] = $url;
            $data['email'] = $req->email;
            $data['title'] = "password Reset";
            $data['body'] = "please click on below link to reset your password";

            $password_reset= DB::table('password_resets')->insert([
                'email' => $data['email'],
                'token' => $token,
                'created_at' => Carbon::now()
            ]);

            Mail::send('forgetPasswordMail',['data'=>$data],function($message) use ($data){
              $message->to($data['email'])->subject($data['title']);
            });


             return response()->json(['succes'=>true,'msg'=>'Please check your mail to reset our password!']);

          }
          else{
            return response()->json(['succes'=>false,'msg'=>'User not found!']);
          }

        }catch(\Exception $e) {
                 return response()->json(['succes'=>false,'msg'=>$e->getMessage()]);
        }
    }

            //reset passwod view load

            public function ResetPasswordLoad(Request $req)
            {
              $resetData = PasswordReset::where('token',$req->token)->get();
              if(isset($req->token) && count($resetData) > 0){

                  $user = User::where('email',$resetData[0]['email'])->get();
                  return view('resetPassword',compact('user'));

              }else{
                return view('404');
              }
            }

            public function resetPassword(Request $req)
            {
              $req->validate([
                  'password' => 'required|string|min:6|confirmed'
              ]);

              $user = User::find($req->id);
              $user->password =Hash::make($req->password);
              $user->save();

              PasswordReset::where('email',$user->email)->delete();

              return "<h1>Your password has been reset succesfully</h1>";
            }

}
