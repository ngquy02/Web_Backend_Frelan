<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\MemberCompany;
use App\Models\Pow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;


class UserController extends Controller
{
    /**
     * Show the currently authenticated user.
     */
    public function show(): JsonResponse
    {   
        $user = Auth::user();
        $currentCompany = Company::where('id', $user->currentCompanyId)->first();
        $userCompany = MemberCompany::where('userId', $user->id)->get();
        $user->currentCompany = $currentCompany;
        $user->userCompanies = $userCompany;

        return response()->json($user);
    }

    public function getAllInfo(Request $request): JsonResponse
    {
        try
        {
            $userData = User::where('username', $request->username)->first();
            $userData->PoW = Pow::where('userId', $userData->id)->get();

            return response()->json($userData, 200);
        }
        catch (\Exception $e)
        {
            return response()->json(['error' => 'Unable to fetch users: ' . $e->getMessage()], 500);
        }
    }

    public function edit(Request $request): JsonResponse
    {
        $requestData = $request->all();
        $id = $request->user()->id;
        $email = $requestData['email'] ?? null;
        $data = array_diff_key($requestData, array_flip(['id', 'email']));

        if (!$id)
        {
            return response()->json(['error' => 'User ID is required.'], 400);
        }

        try
        {
            $user = User::find($id);
            if ($user)
            {
                $user->update($data);   

                return response()->json($user);
            }
            else
            {
                return response()->json(['error' => 'User not found.'], 404);
            }
        }
        catch (\Exception $error)
        {
            Log::error('Error updating user profile: ' . $error->getMessage());

            return response()->json(['error' => 'Error updating user profile.'], 500);
        }
    }
   
    public function getuserCompanies(Request $request): JsonResponse
    {
        
        $userId = $request->userId;

        try
        {
            $userCompany = MemberCompany::where('userId', $userId)
                ->orderBy('updated_at', 'asc')
                ->first();

            if(empty($userCompany))
            {
                return response()->json([]); 
            }

            $result = Company::where('id', $userCompany->companyId)
                ->orderBy('updated_at', 'asc')
                ->first();

            return response()->json([$result]); 
        }
        catch (\Exception $error)
        {
            Log::error('Error occurred: ' . $error->getMessage());

            return response()->json([
                'error' => $error->getMessage(),
                'message' => 'Error occurred while fetching user sponsors.',
            ], 400);
        }
    }
    
    /**
     * Update the currently authenticated user.
     */
    public function update(Request $request): JsonResponse
    {
        $input = $request->all();
        $id = $request->user()->id;
        $updateAttributes = collect($input)->except(['id','username'])->all();
        
        try
        {
            DB::beginTransaction();
            $user = User::find($id);

            if (!$user)
            {
                return response()->json(['message' => "User with ID $id not found"], 404);
            }

            $user->update($updateAttributes);
            $currentCompany = Company::where('id', $user->currentCompanyId)->first();
            $user->currentCompany = $currentCompany;
            DB::commit();

            return response()->json($user);
        }
        catch (\Exception $e)
        {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'update fail'
            ], 401);
        }
    }

    /**
     * Update the password of the currently authenticated user.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()]
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'Password updated.'
        ]);
    }
}
