(function() {
  const isAndroid = (function() {
    return (
      navigator && navigator.userAgentData && navigator.userAgentData.platform && navigator.userAgentData.platform.toLowerCase() === 'android'
    ) || (
      navigator && navigator.platform && navigator.platform.toLowerCase().indexOf('linux') === 0 && ('ontouchstart' in window)
    );
  })();

  if ( ( typeof corona_test_results_qci.month_names === 'undefined') || ( ! corona_test_results_qci.month_names.length ) ) {
    corona_test_results_qci.month_names = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
    if ( window.Intl && window.Intl.DateTimeFormat ) {
      try {
        var formatter = new window.Intl.DateTimeFormat([], { month: 'long', timeZone: 'UTC' });
        var months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(month => {
          var mm = month < 10 ? '0' + month : month;
          return new Date('0000-' + mm + '-01T00:00:00+00:00');
        });
        corona_test_results_qci.month_names = months.map(date => formatter.format(date));
      } catch(e) {}
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    const strtr = function(input, translations) {
      const subStrings = Object.keys(translations);
      return input.split(RegExp(`(${subStrings.join('|')})`))
        .map(function(part) {
          return (typeof translations[part] !== 'undefined' ? translations[part] : part);
        })
        .join('').replace(/  /, ' ')
    };

    /**
     * fill in full address when autocomplete is used
     */
    var autofillTimeout = null;
    var addressTemplate = corona_test_results_qci.address_format;
    var addressField = document.getElementById('ctr-quickcheckin-address');
    var fieldStreet = document.getElementById('ctr-quickcheckin-street');
    var fieldPostcode = document.getElementById('ctr-quickcheckin-postcode');
    var fieldCity = document.getElementById('ctr-quickcheckin-city');
    var fieldState = document.getElementById('ctr-quickcheckin-state');
    var fieldCountry = document.getElementById('ctr-quickcheckin-country');
    var fieldDateOfBirth = document.getElementById('ctr-quickcheckin-dateofbirth');
    var autoFillAddress = function() {
      window.clearTimeout(autofillTimeout);
      autofillTimeout = window.setTimeout(function() {
        // internationalize format
        addressField.value = strtr( addressTemplate, {
          '%streetandnumber': fieldStreet.value,
          '%postcode': fieldPostcode.value,
          '%city': fieldCity.value,
          '%state': fieldState.value,
          '%country': fieldCountry.value
        } );

        // remove empty lines
        addressField.value = addressField.value.replace(/\n\s*\n/g, "\n").trim();

        // empty hidden fields, so autocomplete can be triggered again
        fieldStreet.value = fieldPostcode.value = fieldCity.value = fieldState.value = fieldCountry.value = '';
      }, 1);
    };

    var hiddenInputs = document.getElementsByClassName('ctr-quickcheckin-hidden');
      hiddenInputs = [].slice.call(hiddenInputs);
    if ( hiddenInputs && hiddenInputs.length ) {
      hiddenInputs.forEach(function(h) {
        h.addEventListener('input', autoFillAddress);
      });
    }

    /**
     * code generation
     */
    var escapeVCardValue = function( inputString, escapeQuote ) {
      escapeQuote = !!escapeQuote;
      var escaped = inputString
        .replace(/\\/g, '\\\\')
        .replace(/,/g, '\\,')
        .replace(/;/g, '\\;')
        .replace(/\n/g, '\\n');
      if ( escapeQuote ) {
        escaped = escaped.replace(/"/g, '\\"')
      }
      return escaped;
    };
    var createVCard = function() {
      var rev = new Date(new Date().getTime()).toJSON().slice(0, 19).replace(/[:-]/g, '') + 'Z';
      var e = escapeVCardValue;
      var d = {};
      ['lastname','firstname','dateofbirth','email','phone','address','passport','confirmation_checkbox'].forEach(function(key) {
        var field = document.getElementById('ctr-quickcheckin-' + key);
        if ( (field || {}).type === 'checkbox') {
          d[key] = field.checked ? ( field.value || true ) : false;
        } else if ( field ) {
          d[key] = field.value;
        }
      });

      var extraFields = '';

      return "BEGIN:VCARD\r\n"
        + "VERSION:4.0\r\n"
        + "N:" + e(d.lastname) + ";" + e(d.firstname) + ";;;\r\n"
        + "FN:" + e(d.firstname) + " " + e(d.lastname) + "\r\n"
        + "BDAY:" + e(d.dateofbirth) + "\r\n"
        + "EMAIL:" + e(d.email) + "\r\n"
        + "TEL:" + e(d.phone) + "\r\n"
        + "ADR;TYPE=home;LABEL=\"" + e(d.address, true) + "\":;;;;;;\r\n"
        // + "LABEL:" + e(d.address, true) + "\r\n"
        + "X-PASSPORT:" + e(d.passport) + "\r\n"
        + ( d.confirmation_checkbox ? "X-CONFIRMATION:1" + "\r\n" : '' )
        + extraFields
        + "REV:" + rev +"\r\n"
        + "END:VCARD";
    };

    function validateEmailAddress(emailAddress) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailAddress);
    }

    var generateBtn = document.getElementById('ctr-quickcheckin-generate');
    generateBtn.addEventListener('click', function() {
      const email = document.getElementById('ctr-quickcheckin-email');
      const email_repeat = document.getElementById('ctr-quickcheckin-email-repeat');
      const email_value = email.value.trim();
      const confirmation_checkbox = document.getElementById('ctr-quickcheckin-confirmation_checkbox');

      if ( email_value.length && ! validateEmailAddress( email_value ) ) {
        alert( corona_test_results_qci.message_email_invalid );
        return false;
      } else if ( email_repeat && email_repeat.value.trim() !== email_value ) {
        alert( corona_test_results_qci.message_email_repeat );
        return false;
      } else if ( confirmation_checkbox && confirmation_checkbox.required && ! confirmation_checkbox.checked ) {
        const scrollMargin = 50;
        const cbRect = confirmation_checkbox.getBoundingClientRect();

        const cbIsInViewport =
          cbRect.top >= 0 &&
          cbRect.left >= 0 &&
          cbRect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
          cbRect.right <= (window.innerWidth || document.documentElement.clientWidth);
        if ( ! cbIsInViewport ) {
          const scrollPos = cbRect.top + window.pageYOffset - scrollMargin;
          window.scrollTo({
            top: scrollPos,
            behavior: "smooth"
          });
        }
        alert( corona_test_results_qci.message_checkbox );
        return false;
      }

      var ecl = qrcodegen.QrCode.Ecc.MEDIUM;
      var minVer = 4;
      var maxVer = 40;
      var mask = -1;
      var boostEcc = true;
          var text = createVCard();
          var segs = qrcodegen.QrSegment.makeSegments(text);
          var qr = qrcodegen.QrCode.encodeSegments(segs, ecl, minVer, maxVer, mask, boostEcc);
      var qrCanvas = document.createElement('canvas');

      var scale = 6;
      var border = 4;
      qr.drawCanvas( scale, border, qrCanvas );

      var result = document.getElementById('ctr-quickcheckin-result');
      var image = document.getElementById('ctr-quickcheckin-qrcode');
      var dlLink = document.getElementById('ctr-quickcheckin-save');
      var dataUrl = qrCanvas.toDataURL();
      image.src = dataUrl;
      dlLink.href = dataUrl;
      var timestamp = new Date(new Date().getTime() - new Date().getTimezoneOffset() * 60 * 1000).toJSON().slice(0, 19).replace('T', ' ').replace(/:/g, '-').replace(/ /g, '_');
      dlLink.download = 'test_results_QuickCheckIn_' + timestamp + '.png';
      result.style.display = dlLink.style.display = '';

      image.scrollIntoView && image.scrollIntoView();
    });

    var lastNameField = document.getElementById('ctr-quickcheckin-lastname');
    var fieldStyle = lastNameField ? window.getComputedStyle(lastNameField) : null;

    if ( isAndroid ) {

      var dateWrapper = document.createElement('div');
      dateWrapper.id = 'ctr-qci-date-select-wrapper';
      fieldDateOfBirth.parentNode.appendChild(dateWrapper);

      var dateFieldDay = document.createElement('select');
      var dateFieldMonth = document.createElement('select');
      var dateFieldYear = document.createElement('select');

      var dateFields = [dateFieldYear, dateFieldMonth, dateFieldDay];

      // copy over styles from text input
      if ( fieldStyle ) {
        var styleOptions = [ 'boxSizing', 'backgroundColor', 'padding', 'height', 'font', 'verticalAlign' ];
        styleOptions.filter( function( s ) {
          if ( fieldStyle[s] ) {
            dateFields.filter( function( f ) {
              f.style[s] = fieldStyle[s];
            } );
          }
        });
      }

      var emptyDayOption = document.createElement('option');
      emptyDayOption.value = '';
      emptyDayOption.textContent = corona_test_results_qci.placeholder_day;
      dateFieldDay.add(emptyDayOption);
      var emptyMonthOption = document.createElement('option');
      emptyMonthOption.value = '';
      emptyMonthOption.textContent = corona_test_results_qci.placeholder_month;
      dateFieldMonth.add(emptyMonthOption);
      var emptyYearOption = document.createElement('option');
      emptyYearOption.value = '';
      emptyYearOption.textContent = corona_test_results_qci.placeholder_year;
      dateFieldYear.add(emptyYearOption);

      var dayOptions = [emptyDayOption];
      for ( var i = 1; i <= 31; i++ ) {
        var newOption = document.createElement('option');
        newOption.value = ( i < 10 ? '0' : '' ) + i;
        newOption.textContent = i;
        dayOptions.push( newOption );
        dateFieldDay.add( newOption );
      }

      var monthOptions = [emptyMonthOption];
      for ( var i = 0; i < corona_test_results_qci.month_names.length; i++ ) {
        var newOption = document.createElement('option');
        newOption.value = ( i < 9 ? '0' : '' ) + ( i + 1 );
        newOption.textContent = corona_test_results_qci.month_names[i];
        monthOptions.push(newOption);
        dateFieldMonth.add(newOption);
      }

      var yearEnd = new Date().getFullYear();
      var yearStart = yearEnd - 120;
      var yearOptions = [emptyYearOption];
      for (var i = yearEnd; i >= yearStart; i--) {
        var newOption = document.createElement('option');
        newOption.value = i;
        newOption.textContent = i;
        yearOptions.push(newOption);
        dateFieldYear.add(newOption);
      }

      dateWrapper.appendChild(dateFieldDay);
      dateWrapper.appendChild(dateFieldMonth);
      dateWrapper.appendChild(dateFieldYear);

      var dateChangeHandler = function() {
        // var day = parseInt( dateFieldDay.value, 10 );
        var month = parseInt( dateFieldMonth.value, 10 );
        var year = parseInt( dateFieldYear.value, 10 );
        var maxDays = 31;

        if ( month === 2 ) {
          var isLeapYear = ((year % 4 == 0) && (year % 100 != 0)) || (year % 400 == 0)
          var maxDays = isLeapYear ? 29 : 28;
        } else if ( [2, 4, 6, 9, 11].indexOf( month ) >= 0 ) {
          maxDays = 30;
        }

        dayOptions.filter(function( o, i ) {
          o.hidden = i > maxDays;
          if ( o.hidden && o.selected ) {
            emptyDayOption.selected = true;
          }
        });

        var allSet = true;
        for ( var i = 0; i < dateFields.length; i++ ) {
          var isEmpty = ! dateFields[i].value;
          if ( isEmpty ) {
            allSet = false;
          }
          dateFields[i].classList[isEmpty ? 'add' : 'remove']('show-placeholder');
        }

        if ( allSet ) {
          fieldDateOfBirth.value = dateFields.map(function(field) {
            return field.value;
          }).join('-');
        } else {
          fieldDateOfBirth.value = '';
        }

      };

      if ( fieldDateOfBirth.value ) {
        var ymd = fieldDateOfBirth.value.split('-');
        yearOptions.filter(function(o) {
          if ( o.value === ymd[0]) {
            o.selected = true;
          }
        });
        monthOptions[parseInt(ymd[1], 10)].selected = true;
        dayOptions[parseInt(ymd[2], 10)].selected = true;
      }

      dateFieldDay.addEventListener( 'change', dateChangeHandler );
      dateFieldMonth.addEventListener( 'change', dateChangeHandler );
      dateFieldYear.addEventListener( 'change', dateChangeHandler );
      dateChangeHandler();

      // do this last, so in case anything throws an error, we still have the input as a fallback
      fieldDateOfBirth.type = 'hidden';
    } else {
      if ( fieldStyle ) {
        var styleOptions = [ 'boxSizing', 'backgroundColor', 'padding', 'height', 'font', 'verticalAlign' ];
        styleOptions.filter( function( s ) {
          if ( fieldStyle[s] ) {
            fieldDateOfBirth.style[s] = fieldStyle[s];
          }
        });
        // fix date input height on iOS
        if ( fieldStyle.height ) fieldDateOfBirth.style.minHeight = fieldStyle.height;
      }
    }
  });
})();
