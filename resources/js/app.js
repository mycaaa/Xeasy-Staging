import "./bootstrap";
// chat.js
window.Echo.private(`chat.${window.Laravel.userId}`).listen(
    "MessageSent",
    (e) => {
        if (
            e.message.sender_id == window.Laravel.currentUserId ||
            e.message.receiver_id == window.Laravel.currentUserId
        ) {
            fetchMessages();
        }
    }
);
