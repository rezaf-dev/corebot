<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'bot_id', 'conversation_id', 'chat_message_id', 'session_token', 'vote', 'reason', 'comment'])]
class ChatMessageFeedback extends Model {}
