<!-- Chat Section -->
<div id="chat-section" style="display: none;">
    @php
    $sessionUserId = auth()->user()->id ?? session('user_id') ?? 0;
    @endphp
    <div id="chat-messages" class="remark-chat-container"
        style="max-height:300px; overflow-y:auto; padding:10px;">

        @forelse($remarks as $remark)
        @php
        $isMine = ($remark->By ?? null) == $sessionUserId;
        $name = $remark->name ?? 'NA';
        $message = $remark->Remark ?? '';
        $timestamp = !empty($remark->CrDateTime)
        ? \Carbon\Carbon::parse($remark->CrDateTime)->format('d M Y h:i A')
        : 'Date not available';

        $bubbleClass = $isMine
        ? 'remark-chat-bubble remark-chat-bubble--mine'
        : 'remark-chat-bubble remark-chat-bubble--theirs';
        @endphp

        <div class="d-flex mb-2 {{ $isMine ? 'justify-content-end' : 'justify-content-start' }}">
            <div class="p-2 {{ $bubbleClass }}">
                <small class="text-muted d-flex justify-content-between gap-3" style="font-size: 11px;">
                    <span>{{ $name }}</span>
                    <span>{{ $timestamp }}</span>
                </small>
                <div class="mt-1">
                    {{ $message ?: '—' }}
                </div>

            </div>
        </div>
        @empty
        <p class="text-muted text-center mb-0">No remarks found.</p>
        @endforelse
    </div>
    <!-- Input Box -->
    <div class="border-top bg-white p-3" style="position: sticky; bottom: 0; z-index: 10;">
        <div class="d-flex gap-2">
            <input type="text"
                id="replyMessage"
                class="form-control flex-grow-1"
                placeholder="Type your message..." aria-label="Type your message"
                autocomplete="off">
            <button type="button" id="sendMessage" class="btn btn-primary px-4">
                Send
            </button>
        </div>
    </div>
</div>
@once
<style>
    .remark-chat-container .remark-chat-bubble {
        max-width: 70%;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .remark-chat-container .remark-chat-bubble--mine {
        background: #d1ffd6;
    }

    .remark-chat-container .remark-chat-bubble--theirs {
        background: #f0f0f0;
    }

    #chat-section {
        display: flex;
        flex-direction: column;
        height: 520px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    #chat-messages {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 10px;
        background-color: #f8f9fa;
    }

    #chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    #chat-messages::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    #chat-messages::-webkit-scrollbar-thumb {
        background: #c0c0c0;
        border-radius: 3px;
    }
</style>
@endonce