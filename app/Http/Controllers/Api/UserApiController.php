<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

#[Group('Users', 'CRUD endpoints for managing platform users.')]
class UserApiController extends Controller
{
    /**
     * List users.
     */
    #[Endpoint(
        title: 'List users',
        description: 'Return a paginated list of users, with optional search by name or email.',
    )]
    #[Response(200, description: 'Successful response with paginated users.', type: 'array{data: UserResource[], links: object, meta: object}')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = (string) $request->input('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Show a user.
     */
    #[Endpoint(title: 'Show user', description: 'Return a single user by id.')]
    #[Response(200, description: 'The user.', type: UserResource::class)]
    #[Response(404, description: 'User not found.')]
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles:id,name'));
    }

    /**
     * Create a user.
     */
    #[Endpoint(title: 'Create user', description: 'Create a new user and assign roles by name.')]
    #[Response(201, description: 'The created user.', type: UserResource::class)]
    #[Response(422, description: 'Validation error.')]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });

        return (new UserResource($user->load('roles:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a user.
     */
    #[Endpoint(title: 'Update user', description: 'Update an existing user. Leave password empty to keep it.')]
    #[Response(200, description: 'The updated user.', type: UserResource::class)]
    #[Response(404, description: 'User not found.')]
    #[Response(422, description: 'Validation error.')]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->name = $data['name'];
            $user->email = $data['email'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $user->syncRoles($data['roles'] ?? []);
        });

        return new UserResource($user->load('roles:id,name'));
    }

    /**
     * Delete a user.
     */
    #[Endpoint(title: 'Delete user', description: 'Delete a user. Admins and self cannot be deleted.')]
    #[Response(204, description: 'User deleted.')]
    #[Response(403, description: 'Forbidden.')]
    #[Response(404, description: 'User not found.')]
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * List available roles.
     */
    #[Endpoint(title: 'List roles', description: 'Return all roles available for assignment.')]
    #[Response(200, description: 'The roles list.', type: 'array<int, {id: int, name: string}>')]
    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json(
            Role::query()->orderBy('name')->get(['id', 'name'])
        );
    }
}
