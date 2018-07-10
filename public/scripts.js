var foo;

function update_hides () {
  for (var idx in questions) {
    var q = questions[idx];
    if (q.show_if) {
      var section_id = "s_" + q.id;
      var target_id = "i_" + q.show_if[0];
      var val = $("input[name='"+target_id+"']:checked").val();
      var match = false;
      if (q.show_if.includes (val)) {
	$("#"+section_id).show();
      } else {
	$("#"+section_id).hide();
      }
    }
  }
}

const all_optional = cfg.conf_key == "pace" ? "true" : "false";

function is_required_question_empty (q) {
  if (all_optional)
    return (false);

  if (q.optional)
    return (false);
  
  var input_id = "i_"+q.id;
  if (q.choices) {
    let choice = $("input[name='"+input_id+"']:checked").val();
    if (choice != undefined && choice.trim() != "")
      return (false);
  } else {
    if ($("#"+input_id).val().trim() != "")
      return (false);
  }

  if (q.show_if) {
    var section_id = "s_"+q.id;
    if ($("#"+section_id).is(":hidden"))
      return (false);
  }

  return (true);
}

function apply_submit () {
  for (var idx in questions) {
    var q = questions[idx];
    if (is_required_question_empty (q)) {
      var section_id = "s_" + q.id;
      console.log (section_id);
      var section = $("#"+section_id);
      $(section).find(".required_text").html("required");
      $(window).scrollTop ($(section).offset().top);
      return (false); /* kill submit */
    }
  }
  return (true); /* ok for submit to go through */
}

$(function () {
  $("input[type='radio']").change (update_hides);
  $("#apply_form").submit (apply_submit);

  if (window.questions)
    update_hides ();

});
