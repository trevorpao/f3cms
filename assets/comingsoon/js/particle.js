/**
 * Particleground demo
 * @author Jonathan Nicol - @mrjnicol
 */

$(document).ready(function() {
  $('#particles').particleground({
    dotColor: '#424756',
    lineColor: '#363a49'
  });
  $('.intro').css({
    'margin-top': -($('.intro').height() / 2)
  });
});