/**
 * XP Courier Orders - Admin functionality
 * Handles creation and printing of XP Courier vouchers from order pages
 */
(function ($) {
  "use strict";

  /**
   * Convert base64 string to blob
   */
  function base64ToBlob(base64Str, mimeType = "application/pdf") {
    const bstr = atob(base64Str);
    const n = bstr.length;
    const u8arr = new Uint8Array(n);
    for (let i = 0; i < n; i++) {
      u8arr[i] = bstr.charCodeAt(i);
    }
    return new Blob([u8arr], { type: mimeType });
  }

  /**
   * Open PDF in new window
   */
  function openPdfInNewWindow(base64Pdf) {
    const blob = base64ToBlob(base64Pdf);
    const url = window.URL.createObjectURL(blob);
    window.open(url, "_blank");
  }

  $(function () {
    // Handle create voucher button click
    $(document).on("click", ".apxpc-create-voucher-btn", function (e) {
      e.preventDefault();

      const $btn = $(this);
      const orderId = $btn.data("order-id");
      const accountCode = $btn.data("account-code");
      const $statusMessage = $("#apxpc-status-message");

      // Disable button and show loading state
      $btn.prop("disabled", true);
      const originalText = $btn.text();
      $btn.text("Creating...");

      // Make AJAX request
      $.ajax({
        url: apxpcOrderObj.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "apxpc_create_voucher",
          order_id: orderId,
          account_code: accountCode,
          nonce: apxpcOrderObj.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Show success message
            $statusMessage.html(
              '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Success!</strong> " +
                response.data.message +
                "</div>",
            );

            // If voucher PDF is available, open it
            if (response.data.voucher) {
              setTimeout(function () {
                openPdfInNewWindow(response.data.voucher);
              }, 500);
            }

            // Reload the page to show updated meta fields
            setTimeout(function () {
              location.reload();
            }, 2000);
          } else {
            $statusMessage.html(
              '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Error!</strong> " +
                response.data.message +
                "</div>",
            );
            $btn.prop("disabled", false);
            $btn.text(originalText);
          }
        },
        error: function (xhr, status, error) {
          $statusMessage.html(
            '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
              "<strong>Error!</strong> An unexpected error occurred." +
              "</div>",
          );
          $btn.prop("disabled", false);
          $btn.text(originalText);
        },
      });
    });

    // Handle print voucher button click
    $(document).on("click", "#apxpc-print-voucher-btn", function (e) {
      e.preventDefault();

      const $btn = $(this);
      const orderId = $btn.data("order-id");
      const shipmentNumber = $btn.data("shipment-number");
      const $statusMessage = $("#apxpc-status-message");

      // Disable button and show loading state
      $btn.prop("disabled", true);
      const originalText = $btn.text();
      $btn.text("Loading...");

      // Make AJAX request
      $.ajax({
        url: apxpcOrderObj.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "apxpc_print_voucher",
          order_id: orderId,
          shipment_number: shipmentNumber,
          nonce: apxpcOrderObj.nonce,
        },
        success: function (response) {
          $btn.prop("disabled", false);
          $btn.text(originalText);

          if (response.success) {
            // Show success message
            $statusMessage.html(
              '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Success!</strong> " +
                response.data.message +
                "</div>",
            );

            // Open voucher PDF in new window
            if (response.data.voucher) {
              setTimeout(function () {
                openPdfInNewWindow(response.data.voucher);
              }, 500);
            }
          } else {
            $statusMessage.html(
              '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Error!</strong> " +
                response.data.message +
                "</div>",
            );
          }
        },
        error: function (xhr, status, error) {
          $statusMessage.html(
            '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
              "<strong>Error!</strong> An unexpected error occurred." +
              "</div>",
          );
          $btn.prop("disabled", false);
          $btn.text(originalText);
        },
      });
    });

    // Handle cancel voucher button click
    $(document).on("click", "#apxpc-cancel-voucher-btn", function (e) {
      e.preventDefault();

      const $btn = $(this);
      const orderId = $btn.data("order-id");
      const shipmentNumber = $btn.data("shipment-number");
      const $statusMessage = $("#apxpc-status-message");

      // Confirm before cancelling
      if (!confirm("Are you sure you want to cancel this voucher?")) {
        return;
      }

      // Disable button and show loading state
      $btn.prop("disabled", true);
      const originalText = $btn.text();
      $btn.text("Cancelling...");

      // Make AJAX request
      $.ajax({
        url: apxpcOrderObj.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "apxpc_cancel_voucher",
          order_id: orderId,
          shipment_number: shipmentNumber,
          nonce: apxpcOrderObj.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Show success message
            $statusMessage.html(
              '<div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Success!</strong> " +
                response.data.message +
                "</div>",
            );

            // Reload the page to show updated state
            setTimeout(function () {
              location.reload();
            }, 1500);
          } else {
            $statusMessage.html(
              '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
                "<strong>Error!</strong> " +
                response.data.message +
                "</div>",
            );
            $btn.prop("disabled", false);
            $btn.text(originalText);
          }
        },
        error: function (xhr, status, error) {
          $statusMessage.html(
            '<div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
              "<strong>Error!</strong> An unexpected error occurred." +
              "</div>",
          );
          $btn.prop("disabled", false);
          $btn.text(originalText);
        },
      });
    });
  });
})(jQuery);
