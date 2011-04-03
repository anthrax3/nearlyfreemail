
// Document ready event.

$(document).ready(function()
{
    // Focus on any input box with the "focus" class.
    
    $("input.focus").focus();
    
    // Enable the select-all checkbox in the list view.
    
    $("#select_all").removeAttr("disabled").click(function()
    {
        $("#message_list input[type=checkbox]").attr("checked", $("#select_all").attr("checked"));
    });
    
    // Start autosave, if the compose form is loaded.
    
    var autosave_element = $("#autosave");
    if (autosave_element.size())
    {
        $("#as_enabled").text("Enabled");
        $("#autosave").append('<a id="as_disable" href="javascript:autosave_disable()">(Disable)</a>');
        autosave_interval = setInterval(autosave, 3 * 60 * 1000);  // 3 minutes.
        window.onbeforeunload = function() { autosave(false); };
    }
});

// Generic AJAX form submission function.

function ajax(form, success_callback, failure_callback, complete_callback)
{
    // Identify the form that is trying to submit.
    
    var form = $(form).first();
    
    // Assign the default success and failure callbacks.
    
    if (success_callback === undefined) success_callback = ajax_success_default;
    if (failure_callback === undefined) failure_callback = ajax_failure_default;
    if (complete_callback === undefined) complete_callback = function() { };
    
    // Fire off the AJAX request.
    
    $.ajax({
        "url": form.attr("action"),
        "type": form.attr("method"),
        "data": form.serialize(),
        "processData": true,
        "success": success_callback,
        "error": failure_callback,
        "complete": complete_callback,
        "cache": false,
        "dataType": "json"
    });
    
    // Prevent page refresh.
    
    return false;
}

// Default success callback.

function ajax_success_default(data, textStatus, jqXHR)
{
    switch (data.status)
    {
        case "CONTENT": alert(data.message); break;
        case "REDIRECT": window.location.href = data.location; break;
        case "ERROR": alert(data.message); break;
        default: alert(data.message);
    }
}

// Default failure callback.

function ajax_failure_default(jqXHR, textStatus, errorThrown)
{
    switch (textStatus)
    {
        case "timeout": alert("AJAX Error: The server is not responding."); break;
        case "abort": alert("AJAX Error: The request was aborted."); break;
        case "parsererror": alert("AJAX Error: The server responded with invalid JSON.\n\n" + errorThrown); break;
        default: alert("AJAX Error: " + errorThrown);
    }
}

// Change encoding.

function ajax_change_encoding()
{
    var selected_encoding = $("#change_encoding").val();
    var data = {
        "action": "message_change_encoding",
        "message_id": $("#read_actions_message_id").val(),
        "encoding": selected_encoding
    };
    
    var success_callback = function(data, textStatus, jqXHR)
    {
        switch (data.status)
        {
            case "CONTENT":
                $("#content h3").html(data.content.subject);
                $("#read_content_text").html(data.content.content);
                $("#displayed_encoding").text(selected_encoding);
                break;
            case "ERROR": alert(data.message); break;
            default: alert(data.message);
        }
    };
    
    $.ajax({
        "url": "index.php",
        "type": "get",
        "data": data,
        "processData": true,
        "success": success_callback,
        "error": ajax_failure_default,
        "cache": false,
        "dataType": "json"
    });
    
    return false;
}

// Add a file input to the compose form.

function add_attachment()
{
    var files = $("input[type=file]");
    var new_id = files.size() + 1;
    var new_input = $('<input type="file" name="attach_' + new_id + '" /></div>');
    $('<p />').append(new_input).appendTo($("#add_files_here"));
    return false;
}

// Autosave variables.

var autosave_interval;

// Autosave main function.

function autosave(async)
{
    var data = {
        "action": "compose",
        "button": "autosave",
        "message_id": $("#message_id").val(),
        "recipient": $("#recipient").val(),
        "cc": $("#cc").val(),
        "bcc": $("#bcc").val(),
        "subject": $("#subject").val(),
        "message_content": $("#message_content").val(),
        "references": $("#references").val(),
        "notes": $("#notes").val(),
        "csrf_token": $("#csrf_token").val()
    };
    
    if (async === undefined) async = true;
    if (async == false && data.recipient == "" && data.cc == "" && data.bcc == "" && data.subject == "" && data.message_content == "") return;
    $("#as_enabled").text("Saving..");
    
    var success_callback = function(data, textStatus, jqXHR)
    {
        if (data.status == "CONTENT")
        {
            $("#message_id").val(data.content);
            $("#references").val("");
            $("#notes").val("");
            $("#as_enabled").text("Saved");
        }
        else
        {
            alert(data.content);
            $("#as_enabled").text("Save failed! Will retry in 3 minutes..");
        }
    };
    
    var failure_callback = function(jqXHR, textStatus, errorThrown)
    {
        $("#as_enabled").text("Save failed! Will retry in 3 minutes..");
    };
    
    var complete_callback = function(jqXHR, textStatus)
    {
        setTimeout(function() { $("#as_enabled").text("Enabled"); }, 5000);  // 3 seconds.
    }
    
    $.ajax({
        "url": "index.php",
        "type": "post",
        "async": async,
        "data": data,
        "processData": true,
        "success": success_callback,
        "error": failure_callback,
        "complete": complete_callback,
        "cache": false,
        "dataType": "json"
    });
}

// Disable autosave.

function autosave_disable()
{
    clearInterval(autosave_interval);
    window.onbeforeunload = function() { };
    $("#as_enabled").text("Disabled");
    $("#as_disable").remove();
}

// Disable autosave just before form submit.

function autosave_override()
{
    window.onbeforeunload = function() { };
}
