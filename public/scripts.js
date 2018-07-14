var params = (new URL(location)).searchParams;
var show_all = parseInt (params.get ("show_all"));
if (isNaN (show_all))
  show_all = 0;

var all_optional = cfg.conf_key == "pace" ? 1 : 0;
//all_optional = 0;

function update_hides () {
  for (var idx in questions) {
    var q = questions[idx];
    var want_field;
    if (q.show_if) {
      want_field = false;
      var target_id = "i_" + q.show_if[0];
      var val = $("input[name='"+target_id+"']:checked").val();
      if (q.show_if.includes (val)) {
	want_field = true;
      }
    } else {
      want_field = true;
    }
    if (show_all)
      want_field = true;
    
    var section_id = "s_" + q.id;
    if (want_field) {
      $("#"+section_id).show();
    } else {
      $("#"+section_id).hide();
    }
  }
}


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

function do_lookup_box_change (ev) {
  console.log (ev);
  return (true);
}

function do_add_another (ev) {
  let elt = $(ev.target).parents(".question").find(".input_wrapper");
  $(elt).append("<div><input type='text' name='extra_people[]'"+
		" class='lookup_individual'"+
		" size='40' /></div>");
  $(".lookup_individual").autocomplete({ source: "lookup_individual.php" });
  $(".lookup_individual").attr("autocomplete","correspondent-name");

}

$(function () {
  $("input[type='radio']").change (update_hides);
  $("#apply_form").submit (apply_submit);
  $(".lookup_box").change (do_lookup_box_change);
  
  if (window.questions)
    update_hides ();

  $(".lookup_individual").autocomplete({ source: "lookup_individual.php" });
  $(".lookup_individual").attr("autocomplete","correspondent-name");

  $(".lookup_group").autocomplete({ source: "lookup_group.php" });
  $(".lookup_group").attr("autocomplete","correspondent-name");

  $("#add_another").click(do_add_another);

  if (show_all)
    $(".condition").show();
});
