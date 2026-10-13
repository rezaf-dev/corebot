<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;

class ChatConversationPolicy
{
    public function view(User $user, ChatConversation $conversation): bool
    {
        return $user->hasWorkspaceAccess() && ($user->isSuperAdmin() || $user->tenant_id === $conversation->tenant_id);
    }

    public function update(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation) && ($user->isAdmin() || $conversation->assigned_to === $user->id);
    }

    public function claim(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->isAdmin();
    }
}
