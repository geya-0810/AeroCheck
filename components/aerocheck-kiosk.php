<?php
require_once __DIR__ . '/../SelfServiceKiosk.php';
$kiosk = new SelfServiceKiosk('KIOSK001', 'Main Hall');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    switch ($_POST['action']) {
        case 'find_booking':
            $booking_ref = strtoupper(trim($_POST['booking_ref'] ?? ''));
            $last_name = trim($_POST['last_name'] ?? '');
            $result = $kiosk->findBooking($booking_ref, $last_name);
            if ($result) {
                echo json_encode(['success' => true, 'booking' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found. Please verify information or contact staff.']);
            }
            exit;
        case 'get_seats':
            $flight_number = trim($_POST['flight_number'] ?? '');
            $seats = $kiosk->getAvailableSeats($flight_number);
            echo json_encode(['success' => true, 'seats' => $seats]);
            exit;
        case 'get_all_seats':
            $flight_number = trim($_POST['flight_number'] ?? '');
            $seats = $kiosk->getAllSeats($flight_number);
            echo json_encode(['success' => true, 'seats' => $seats]);
            exit;
        case 'process_checkin':
            $bookingRef = $_POST['booking_ref'] ?? '';
            $selectedPassengers = isset($_POST['selected_passengers']) ? json_decode($_POST['selected_passengers'], true) : [];
            $passengerSeats = isset($_POST['passenger_seats']) ? json_decode($_POST['passenger_seats'], true) : [];
            $baggageInfo = isset($_POST['baggage_info']) ? json_decode($_POST['baggage_info'], true) : [];
            $specialNeeds = isset($_POST['special_needs']) ? json_decode($_POST['special_needs'], true) : [];
            $success = $kiosk->processSelfCheckIn($bookingRef, $selectedPassengers, $passengerSeats, $baggageInfo, $specialNeeds);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Check-in successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Check-in failed, please try again']);
            }
            exit;
        case 'get_baggage_packages':
            $packages = $kiosk->getBaggagePackages();
            echo json_encode(['success'=>true,'packages'=>$packages]);
            exit;
    }
}
?>
<!DOCTYPE html>
    <html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($kiosk_config['title']); ?></title>
    <link rel="stylesheet" href="aerocheck-kiosk.css">
</head>

<body>
    <div class="kiosk-container">

        <div class="header">
            <h1>AeroCheck Self Check-in System</h1>
            <p class="subtitle">Welcome to AeroCheck Self Check-in System</p>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div> 

        <div class="main-content">
            <!-- Page 1: Welcome Screen -->
            <div class="page active" id="page1">
                <div class="welcome-content">
                    <div class="logo">✈️ AeroCheck</div>
                    <div class="welcome-text">Welcome to AeroCheck Self Check-in System</div>
                    <div class="start-text">Tap anywhere to start</div>
                </div>
            </div>

            <!-- Page 2: Booking Reference Input -->
            <div class="page" id="page2">
                <div class="form-container">
                    <h2 class="booking-form-title">
                        Find Booking
                    </h2>
                    <div class="form-group">
                        <label for="bookingRef">Please enter your Booking Reference</label>
                        <input type="text" id="bookingRef" placeholder="" class="booking-ref-input">
                    </div>
                    <div class="form-group">
                        <label for="lastName">Please enter your Last Name</label>
                        <input type="text" id="lastName" placeholder="Last Name">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary btn-large" onclick="findBooking()">
                            Find Booking
                        </button>
                    </div>
                    <div id="errorMessage" class="error-message error-message-hidden"></div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" onclick="goToPage(1)">Back</button>
                    <div></div>
                </div>
            </div>

            <!-- Page 3: Booking Information Confirmation -->
            <div class="page" id="page3">
                <div class="booking-info">
                    <h3>Here is your booking information. Please confirm.</h3>
                    <div class="info-grid" id="bookingDetails">
                        <!-- Booking information will be filled by JavaScript -->
                    </div>
                </div>
                <div class="passenger-list">
                    <h3>Please select the passengers to check in.</h3>
                    <div id="passengerList">
                        <!-- Passenger list will be filled by JavaScript -->
                    </div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" onclick="goToPage(2)">Back</button>
                    <button class="btn btn-primary" onclick="proceedToBaggage()">Next</button>
                </div>
            </div>

            <!-- Page 4: Baggage Check-in -->
            <div class="page" id="page4">
                <div class="baggage-form">
                    <h2>Baggage Check-in Information</h2>
                    <div id="baggageItemsContainer"></div>
                    <div class="baggage-summary">
                        <div><strong>Total Weight:</strong> <span id="baggageWeightDisplay">0</span> kg</div>
                        <div><strong>Items:</strong> <span id="baggageCountDisplay">0</span></div>
                    </div>
                    <div class="baggage-form-group">
                        <label>Please select baggage package:</label>
                        <div class="baggage-package-container">
                        <div class="baggage-package-select-wrapper">
                            <select id="baggagePackageSelect" class="baggage-package-select"></select>
                            <div id="baggagePackageDesc" class="baggage-package-desc"></div>
                        </div>
                        <div id="baggageActionArea">
                            <button class="btn btn-pay" id="payBaggageBtn" onclick="payForBaggage()" disabled>Pay</button>
                        </div>
                    </div>
                    </div>
                    <div id="baggagePackageError" class="error-message baggage-package-error"></div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" onclick="goToPage(3)">Back</button>
                    <button class="btn btn-primary" onclick="proceedToSpecialNeeds()">Next</button>
                </div>
            </div>

            <!-- Page 5: Special Needs -->
            <div class="page" id="page5">
                <div class="special-needs">
                    <h3>Special Needs Assistance</h3>
                    <p class="special-needs-text">
                        Do you or your group members require special assistance?
                    </p>
                    <div id="specialOptionsContainer" class="special-options-visible">
                        <h4>Please select the required assistance type:</h4>
                        <div class="special-options">
                            <div class="special-option">
                                <input type="checkbox" id="wheelchair" value="wheelchair">
                                <label for="wheelchair">Wheelchair Assistance</label>
                            </div>
                            <div class="special-option">
                                <input type="checkbox" id="hearing" value="hearing">
                                <label for="hearing">Hearing Impaired Assistance</label>
                            </div>
                            <div class="special-option">
                                <input type="checkbox" id="visual" value="visual">
                                <label for="visual">Visually Impaired Assistance</label>
                            </div>
                            <div class="special-option">
                                <input type="checkbox" id="medical" value="medical">
                                <label for="medical">Medical Equipment</label>
                            </div>
                            <div class="special-option">
                                <input type="checkbox" id="infant" value="infant">
                                <label for="infant">Infant Bassinet</label>
                            </div>
                            <div class="special-option">
                                <input type="checkbox" id="other" value="other">
                                <label for="other">Other Needs</label>
                            </div>
                        </div>
                        <div class="form-group special-needs-form-group">
                            <label for="specialNotes">Additional Notes:</label>
                            <input type="text" id="specialNotes" placeholder="Please enter brief description">
                        </div>
                    </div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" onclick="goToPage(4)">Back</button>
                    <button class="btn btn-primary" onclick="proceedToReview()">Next</button>
                </div>
            </div>

            <!-- Page 6: Information Review -->
            <div class="page" id="page6">
                <div class="review-section">
                    <h3>Review Check-in Information</h3>
                    <p class="review-warning">
                        Please carefully review the following information. If correct, please click 'Confirm and Proceed'
                    </p>
                    <div id="reviewContent">
                        <!-- Review content will be filled by JavaScript -->
                    </div>
                </div>
                <div class="navigation">
                    <button class="btn btn-secondary" onclick="goToPage(5)">Modify</button>
                    <button class="btn btn-primary btn-large" onclick="confirmCheckIn()">Confirm and Proceed</button>
                </div>
            </div>

            <!-- Page 7: Boarding Pass -->
            <div class="page" id="page7">
                <div class="boarding-pass">
                    <h3>Boarding Pass Ready</h3>
                    <p class="boarding-pass-success">
                        Your boarding pass has been successfully generated!
                    </p>
                    <div class="boarding-pass-visual">
                        <h4>AeroCheck Boarding Pass</h4>
                        <div id="boardingPassDetails">
                            <!-- Boarding pass details will be filled by JavaScript -->
                        </div>
                    </div>
                    <div class="form-group boarding-pass-form-group">
                        <label for="mobileNumber">Mobile Number:</label>
                        <input type="tel" id="mobileNumber" placeholder="+60123456789">
                        <button class="btn btn-primary send-eboarding-btn" onclick="sendEBoardingPass()">
                            Send e-Boarding Pass to Mobile
                        </button>
                    </div>
                    <div id="sendMessage" class="send-message"></div>
                    <div class="important-notice">
                        <p><strong>Important Notice:</strong></p>
                        <p>• Please keep your boarding pass and baggage receipt safe</p>
                        <p>• Please pay attention to flight status updates on airport screens</p>
                    </div>
                </div>
                <div class="navigation">
                    <button class="btn btn-primary btn-large" onclick="finishCheckIn()">Done</button>
                </div>
            </div>

            <!-- Page 8: Thank You Page -->
            <div class="page" id="page8">
                <div class="welcome-content">
                    <div class="logo">✈️ Thank You!</div>
                    <div class="welcome-text">Thank you for using AeroCheck Self Check-in System!</div>
                    <div class="start-text">Have a pleasant journey!</div>
                </div>
            </div>
        </div>
    </div>
    <script>
  // Global variables
  let currentPage = 1;
  let activeInput = null;
  let bookingData = null;
  let flightData = null;
  let selectedPassengers = [];
  let currentSeatPassenger = 0;
  let passengerSeats = {};
  let baggageInfo = {};
  let specialNeedsData = {};
  let passengersData = []; // Store passenger data
  let baggagePackages = []; // Baggage package data
  let baggageItems = []; // Page 4: Dynamic baggage items
  let baggagePaid = false; // Payment status
  let amountPaid = 0; // Amount paid
  let paidPackageId = null; // Paid package ID

  // Initialize
  document.addEventListener("DOMContentLoaded", function () {
    updateProgress();
    document
      .getElementById("page1")
      .addEventListener("click", () => goToPage(2));
  });

  // Page navigation (goToPage is rewritten at the end of file)
  let origGoToPage; // Define a variable to save the original function

  function originalGoToPage(pageNum) {
    document
      .querySelectorAll(".page")
      .forEach((page) => page.classList.remove("active"));
    document.getElementById(`page${pageNum}`).classList.add("active");
    currentPage = pageNum;
    updateProgress();
  }

  // Initialize and assign the original function to origGoToPage
  document.addEventListener("DOMContentLoaded", () => {
    origGoToPage = originalGoToPage;
  });

  function updateProgress() {
    const progress = ((currentPage - 1) / 7) * 100;
    document.getElementById("progressFill").style.width = progress + "%";
  }

    // Pages 2 & 3: Booking and passenger selection logic (no major changes)
    function findBooking() {
        const bookingRef = document.getElementById('bookingRef').value.toUpperCase();
        const lastName = document.getElementById('lastName').value;
        const errorDiv = document.getElementById('errorMessage');
        errorDiv.classList.remove('error-visible');
        if (!bookingRef || !lastName) {
            showError('Please enter complete information');
            return;
        }
        const formData = new FormData();
        formData.append('action', 'find_booking');
        formData.append('booking_ref', bookingRef);
        formData.append('last_name', lastName);
        fetch('', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bookingData = data.booking.booking || data.booking;
                    const passengers = data.booking.passengers || [];
                    flightData = data.booking.flight || {};
                    window.passengersData = passengers;
                    passengersData = passengers;
                    displayBookingInfo(bookingData, passengers, flightData);
                    goToPage(3);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('System error, please try again');
            });
    }

  function showError(message) {
    const errorDiv = document.getElementById("errorMessage");
    errorDiv.textContent = message;
    errorDiv.classList.add("error-visible");
  }

  function displayBookingInfo(bookingData, passengers, flight) {
    const detailsDiv = document.getElementById("bookingDetails");
    detailsDiv.innerHTML = `
            <div class="info-item"><strong>Booking Reference:</strong> ${
              bookingData.booking_id || "N/A"
            }</div>
            <div class="info-item"><strong>Flight Number:</strong> ${
              flight.flight_number || "N/A"
            }</div>
            <div class="info-item"><strong>Destination:</strong> ${
              flight.destination || "N/A"
            }</div>
            <div class="info-item"><strong>Departure Time:</strong> ${
              flight.departure_time || "N/A"
            }</div>
            <div class="info-item"><strong>Fare Class:</strong> ${
              bookingData.fare_class || "N/A"
            }</div>
            <div class="info-item"><strong>Gate:</strong> ${
              flight.gate || "N/A"
            }</div>`;

    const passengerListDiv = document.getElementById("passengerList");
    passengerListDiv.innerHTML = "";
    if (passengers.length > 1) {
      const groupNotice = document.createElement("p");
      groupNotice.innerHTML =
        "<strong>This is a group booking. Please select the members to check in.</strong>";
      passengerListDiv.appendChild(groupNotice);
    }
    passengers.forEach((passenger) => {
      const passengerDiv = document.createElement("div");
      passengerDiv.className = `passenger-item ${
        passenger.checkedIn || passenger.check_in_status === "Checked In"
          ? "checked-in"
          : ""
      }`;
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.value = passenger.id || passenger.passenger_id;
      checkbox.disabled =
        passenger.checkedIn || passenger.check_in_status === "Checked In";
      checkbox.onchange = updateSelectedPassengers;
      const label = document.createElement("label");
      const passportNumber =
        passenger.passportNumber || passenger.passport_number || "";
      const lastName = passenger.lastName || passenger.last_name || "";
      const firstName = passenger.firstName || passenger.first_name || "";
      const isCheckedIn =
        passenger.checkedIn || passenger.check_in_status === "Checked In";
      label.innerHTML = `<strong>${lastName}, ${firstName}</strong> (Passport Last 4 digits: ${
        passportNumber.slice(-4) || "N/A"
      }) - ${isCheckedIn ? "Checked In" : "Not Checked In"}`;
      passengerDiv.appendChild(checkbox);
      passengerDiv.appendChild(label);
      passengerListDiv.appendChild(passengerDiv);
    });
  }

  function updateSelectedPassengers() {
    selectedPassengers = [];
    const checkboxes = document.querySelectorAll(
      '#passengerList input[type="checkbox"]:checked'
    );
    checkboxes.forEach((checkbox) => {
      selectedPassengers.push(checkbox.value);
    });
  }

  // Assign seats based on fare class
  function assignSeatByFareClass(passenger, fareClass) {
    for (let seat of window.seatsData || []) {
      if (
        seat.status === "Available" &&
        seat.seat_class === fareClass &&
        !Object.values(passengerSeats).includes(seat.seat_number)
      ) {
        passengerSeats[passenger.id || passenger.passenger_id] = seat.seat_number;
        break;
      }
    }
  }

  function proceedToBaggage() {
    if (!selectedPassengers || selectedPassengers.length === 0) {
      alert("Please select at least one passenger");
      return;
    }
    const formData = new FormData();
    formData.append("action", "get_all_seats");
    formData.append("flight_number", flightData.flight_number);
    fetch("", { method: "POST", body: formData })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.seatsData = data.seats;
          selectedPassengers.forEach((pid) => {
            const p = passengersData.find(
              (pp) => String(pp.id || pp.passenger_id) === String(pid)
            );
            if (!p) return;
            assignSeatByFareClass(p, bookingData.fare_class);
          });
          goToPage(4);
        } else {
          alert("Failed to get seat data");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("System error, please try again");
      });
  }

  // Page 4: Baggage check-in core logic
  // [MODIFIED] Get baggage packages, return Promise to ensure subsequent operations execute after completion
  function fetchBaggagePackages() {
    return fetch("", {
      method: "POST",
      body: new URLSearchParams("action=get_baggage_packages"),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          // Sort by weight from small to large
          baggagePackages = data.packages.sort(
            (a, b) =>
              parseFloat(a.additional_weight_kg) -
              parseFloat(b.additional_weight_kg)
          );
        }
      });
  }

  // [MODIFIED] Page 4 initialization logic, defined in goToPage rewrite at end of file

  // [MODIFIED] Update baggage items, add 40kg total weight limit
  function updateBaggageItem(idx, field, value) {
    const originalValue = baggageItems[idx][field];
    baggageItems[idx][field] = value; // Temporarily update

    if (field === "weight") {
      let totalWeight = 0;
      baggageItems.forEach((item) => {
        totalWeight += parseFloat(item.weight) || 0;
      });

      if (totalWeight > 40) {
        alert(
          "Total baggage weight cannot exceed 40kg."
        );
        baggageItems[idx][field] = originalValue; // Revert changes
        renderBaggageItems(); // Re-render to restore input field values
        return;
      }
    }

    // Any modification after payment means payment status needs to be reconfirmed
    if (amountPaid > 0) {
      baggagePaid = false;
    }

    renderBaggageSummary();
  }

  // [MODIFIED] Core logic for automatic package selection
  function updateBaggagePackageOptions(totalWeight) {
    const select = document.getElementById("baggagePackageSelect");
    if (!select) return;

    // Find all packages that can handle current weight
    let suitablePackages = baggagePackages.filter(pkg => parseFloat(pkg.additional_weight_kg) >= totalWeight);
    // If paid, disable packages below paidPackageId
    let paidIdx = paidPackageId ? baggagePackages.findIndex(pkg => pkg.package_id === paidPackageId) : -1;

    // Save previously selected value
    const previousValue = select.value;
    select.innerHTML = "";

    if (suitablePackages.length > 0) {
      // Add all suitable packages to dropdown
      baggagePackages.forEach((pkg, idx) => {
        const price = parseFloat(pkg.price).toFixed(2);
        let disabled = '';
        if (paidIdx >= 0 && idx < paidIdx) disabled = 'disabled';
        // Only show packages that can handle current weight
        if (parseFloat(pkg.additional_weight_kg) >= totalWeight) {
          select.innerHTML += `<option value="${pkg.package_id}" ${disabled}>${pkg.package_name} - ${pkg.additional_weight_kg}kg - RM${price}</option>`;
        } else if (paidIdx >= 0 && idx === paidIdx) {
          // Allow paid package to be selectable when weight is reduced
          select.innerHTML += `<option value="${pkg.package_id}">${pkg.package_name} - ${pkg.additional_weight_kg}kg - RM${price}</option>`;
        }
      });
      // Automatically select most suitable package
      let autoValue = previousValue;
      // If current weight is greater than paid package, auto-select nearest higher package
      if (paidIdx >= 0) {
        const minIdx = baggagePackages.findIndex(pkg => parseFloat(pkg.additional_weight_kg) >= totalWeight);
        if (minIdx > paidIdx) autoValue = baggagePackages[minIdx].package_id;
        else autoValue = paidPackageId;
      } else {
        // When unpaid, auto-select minimum available
        autoValue = suitablePackages[0].package_id;
      }
      select.value = autoValue;
    } else if (totalWeight > 0) {
      select.innerHTML = `<option value="" disabled>No available packages</option>`;
    } else {
      // When weight is 0, show all packages
      baggagePackages.forEach((pkg, idx) => {
        const price = parseFloat(pkg.price).toFixed(2);
        let disabled = '';
        if (paidIdx >= 0 && idx < paidIdx) disabled = 'disabled';
        select.innerHTML += `<option value="${pkg.package_id}" ${disabled}>${pkg.package_name} - ${pkg.additional_weight_kg}kg - RM${price}</option>`;
      });
    }

    // If auto-selection result is same as before, no need to trigger onchange
    if (select.value !== previousValue) {
      select.dispatchEvent(new Event("change"));
    }
  }

  // [MODIFIED] Update payment button state, handle price difference logic
  function updatePayButtonState(totalWeight, count) {
    const pkgSelect = document.getElementById("baggagePackageSelect");
    const payBtn = document.getElementById("payBaggageBtn");
    if (!payBtn || !pkgSelect) return;

    const selectedPkgId = pkgSelect.value;
    const selectedPkg = baggagePackages.find(
      (p) => p.package_id === selectedPkgId
    );

    const green = "#2ecc71",
      blue = "#3498db",
      gray = "#bdc3c7";

    // Allow users to continue editing after payment
    pkgSelect.disabled = false;
    document
      .querySelectorAll(
        ".baggage-weight-input, .baggage-handling-select, .baggage-add-btn, .baggage-remove-btn, .baggage-owner-select"
      )
      .forEach((el) => (el.disabled = false));

    if (count === 0 || !selectedPkg) {
      payBtn.disabled = true;
      payBtn.innerHTML = "Pay";
      payBtn.style.background = gray;
      return;
    }

    const price = parseFloat(selectedPkg.price);
    const priceDifference = price - amountPaid;

    if (priceDifference > 0) {
      // Need to pay or pay price difference
      payBtn.disabled = false;
      baggagePaid = false; // Mark as unpaid status
      if (amountPaid > 0) {
        payBtn.innerHTML = `Pay RM ${priceDifference.toFixed(2)}`;
      } else {
        payBtn.innerHTML = `Pay RM ${price.toFixed(2)}`;
      }
      payBtn.style.background = blue;
    } else {
      // No additional payment needed (e.g., downgrade package)
      payBtn.disabled = true;
      payBtn.innerHTML = "Paid";
      payBtn.style.background = green;
      baggagePaid = true; // Mark as paid status
    }
  }

  // [MODIFIED] Payment function, update amountPaid
  function payForBaggage() {
    const payBtn = document.getElementById("payBaggageBtn");
    const pkgSelect = document.getElementById("baggagePackageSelect");
    if (!payBtn || payBtn.disabled || !pkgSelect.value) return;

    const selectedPkg = baggagePackages.find(p => p.package_id === pkgSelect.value);
    if (!selectedPkg) return;

    payBtn.disabled = true;
    payBtn.innerHTML = '<span class="spinner"></span> Paying...';

    setTimeout(() => {
      amountPaid = parseFloat(selectedPkg.price);
      baggagePaid = true; // Mark as paid
      paidPackageId = selectedPkg.package_id; // Record paid package
      renderBaggageSummary(); // Re-render, button will become "Paid"
    }, 1800);
  }

  // [MODIFIED] Page 4 Next button validation logic
  function page4Next() {
    const count =
      parseInt(document.getElementById("baggageCountDisplay").textContent) || 0;
    const errorDiv = document.getElementById("baggagePackageError");
    const payBtn = document.getElementById("payBaggageBtn");

    // If there's baggage and payment button is active, it means there's unpaid amount
    if (count > 0 && !payBtn.disabled) {
      errorDiv.textContent =
        "Please complete payment first";
      errorDiv.style.display = "block";
      return;
    }

    errorDiv.style.display = "none";
    goToPage(5);
  }

  // Functions to render and manage baggage items (no major changes)
  function renderBaggageItems() {
    const container = document.getElementById("baggageItemsContainer");
    container.innerHTML = "";
    baggageItems.forEach((item, idx) => {
      const div = document.createElement("div");
      div.className = "baggage-item-row";
      let ownerInput = "";
      if (selectedPassengers.length === 1) {
        let p =
          item.owner ||
          passengersData.find(
            (pp) =>
              String(pp.id || pp.passenger_id) === String(selectedPassengers[0])
          ) ||
          null;
        item.owner = p;
        ownerInput = `<input type="text" value="${
          p
            ? (p.firstName || p.first_name || "") +
              " " +
              (p.lastName || p.last_name || "")
            : ""
        }" class="baggage-owner-input" disabled>`;
      } else if (selectedPassengers.length > 1) {
        ownerInput = `<select class="baggage-owner-select" onchange="updateBaggageItem(${idx}, 'owner', passengersData.find(p => String(p.id||p.passenger_id) === this.value))">`;
        selectedPassengers.forEach((pid) => {
          const p = passengersData.find(
            (pp) => String(pp.id || pp.passenger_id) === String(pid)
          );
          const pname = p
            ? (p.firstName || p.first_name || "") +
              " " +
              (p.lastName || p.last_name || "")
            : pid;
          const isSelected =
            item.owner && (item.owner.id || item.owner.passenger_id) == pid;
          ownerInput += `<option value="${pid}" ${
            isSelected ? "selected" : ""
          }>${pname}</option>`;
        });
        ownerInput += "</select>";
      } else {
        ownerInput = `<input type="text" value="" class="baggage-owner-input" disabled>`;
      }
      div.innerHTML = `
                <input type="number" min="0" max="40" step="0.1" placeholder="KG" value="${
                  item.weight || ""
                }" class="baggage-weight-input">
                ${ownerInput}
                <select class="baggage-handling-select">
                    <option value="None" ${
                      item.handling === "None" ? "selected" : ""
                    }>None</option>
                    <option value="Fragile" ${
                      item.handling === "Fragile" ? "selected" : ""
                    }>Fragile</option>
                    <option value="Oversized" ${
                      item.handling === "Oversized" ? "selected" : ""
                    }>Oversized</option>
                    <option value="Valuable" ${
                      item.handling === "Valuable" ? "selected" : ""
                    }>Valuable</option>
                    <option value="Perishable" ${
                      item.handling === "Perishable" ? "selected" : ""
                    }>Perishable</option>
                </select>
                <button class="btn btn-danger baggage-remove-btn" onclick="removeBaggageItem(${idx})">🗑️</button>
                <button class="btn baggage-add baggage-add-btn" onclick="addBaggageItemAfter(${idx})">+</button>`;

      div.querySelector(".baggage-weight-input").onchange = (e) =>
        updateBaggageItem(idx, "weight", e.target.value);
      if (selectedPassengers.length <= 1) {
        // onchange is handled by the element itself if it's a select
      }
      div.querySelector(".baggage-handling-select").onchange = (e) =>
        updateBaggageItem(idx, "handling", e.target.value);
      container.appendChild(div);
    });
    if (baggageItems.length === 0) {
      addBaggageItem();
    }
    renderBaggageSummary();
  }

  function addBaggageItem() {
    let ownerObj = null;
    if (selectedPassengers.length > 0) {
      ownerObj =
        passengersData.find(
          (pp) =>
            String(pp.id || pp.passenger_id) === String(selectedPassengers[0])
        ) || null;
    }
    baggageItems.push({ weight: "", owner: ownerObj, handling: "None" });
    renderBaggageItems();
  }

  function addBaggageItemAfter(idx) {
    let ownerObj = baggageItems[idx].owner; // Default to same owner
    baggageItems.splice(idx + 1, 0, {
      weight: "",
      owner: ownerObj,
      handling: "None",
    });
    renderBaggageItems();
  }

  function removeBaggageItem(idx) {
    if (baggageItems.length > 1) {
      baggageItems.splice(idx, 1);
      renderBaggageItems();
    } else {
      // If it's the last item, just clear the weight
      baggageItems[0].weight = "";
      renderBaggageItems();
    }
  }

  function renderBaggageSummary() {
    let total = 0,
      count = 0;
    baggageItems.forEach((item) => {
      const weight = parseFloat(item.weight);
      if (!isNaN(weight) && weight > 0) {
        total += weight;
        count++;
      }
    });
    document.getElementById("baggageCountDisplay").textContent = count;
    document.getElementById("baggageWeightDisplay").textContent =
      total.toFixed(1);

    // Call new core functions
    updateBaggagePackageOptions(total);
    updatePayButtonState(total, count);
  }

  // Pages 5, 6, 7, 8 logic (no changes)
  function proceedToReview() {
    updateBaggageInfo(); // New: Sync baggage information
    const specialOptions = document.querySelectorAll(
      '#specialOptionsContainer input[type="checkbox"]:checked'
    );
    const specialNotes = document.getElementById("specialNotes").value;
    specialNeedsData = {
      hasSpecialNeeds: specialOptions.length > 0,
      needs: Array.from(specialOptions).map((option) => option.value),
      notes: specialNotes,
    };
    displayReviewInfo();
    goToPage(6);
  }

  function displayReviewInfo() {
    const reviewDiv = document.getElementById("reviewContent");
    reviewDiv.innerHTML = "";

    // Flight information
    const flightInfo = document.createElement("div");
    flightInfo.className = "review-item";
    flightInfo.innerHTML = `
            <h4>Flight Information</h4>
            <p><strong>Flight Number:</strong> ${
              flightData.flight_number || "N/A"
            }</p>
            <p><strong>Destination:</strong> ${
              flightData.destination || "N/A"
            }</p>
            <p><strong>Departure Time:</strong> ${
              flightData.departure_time || "N/A"
            }</p>
            <p><strong>Gate:</strong> ${flightData.gate || "N/A"}</p>
        `;
    reviewDiv.appendChild(flightInfo);

    // Count baggage for each passenger
    selectedPassengers.forEach((passengerId) => {
      const passenger = passengersData.find(
        (p) => (p.id || p.passenger_id) === passengerId
      );
      // Count baggage for this passenger
      const passengerBaggageCount = baggageItems.filter(item =>
        item.owner && (item.owner.id || item.owner.passenger_id) === passengerId && parseFloat(item.weight) > 0
      ).length;
      const passengerInfo = document.createElement("div");
      passengerInfo.className = "review-item";
      passengerInfo.innerHTML = `
                <h4>Passenger Information</h4>
                <p><strong>Name:</strong> ${
                  passenger.firstName || passenger.first_name || ""
                } ${passenger.lastName || passenger.last_name || ""}</p>
                <p><strong>Passport Number:</strong> ${
                  passenger.passportNumber || passenger.passport_number || ""
                }</p>
                <p><strong>Contact Phone:</strong> ${
                  passenger.phone || passenger.contact_phone || ""
                }</p>
                <p><strong>Seat Number:</strong> ${
                  passengerSeats[passengerId]
                }</p>
                <p><strong>Checked Baggage:</strong> ${
                  passengerBaggageCount
                } items</p>
            `;
      reviewDiv.appendChild(passengerInfo);
    });

    // Other Information / Details
    const otherInfo = document.createElement("div");
    otherInfo.className = "review-item";
    otherInfo.innerHTML = `
      <h4>Other Information</h4>
      <p><strong>Baggage Package:</strong> ${baggageInfo.packageName || "N/A"}</p>
      <p><strong>Special Needs:</strong> ${
        specialNeedsData.hasSpecialNeeds ? specialNeedsData.needs.join(", ") : "None"
      }</p>
      ${specialNeedsData.notes ? `<p><strong>Notes:</strong> ${specialNeedsData.notes}</p>` : ""}
    `;
    reviewDiv.appendChild(otherInfo);
  }

  function confirmCheckIn() {
    // Get confirm button and disable it
    const confirmBtn = document.querySelector(
      'button[onclick="confirmCheckIn()"]'
    );
    confirmBtn.disabled = true;
    confirmBtn.textContent = "Processing...";

    // Prepare data
    const checkInData = {
      action: 'process_checkin',
      booking_ref: bookingData.booking_id || bookingData.bookingId,
      selected_passengers: JSON.stringify(selectedPassengers),
      passenger_seats: JSON.stringify(passengerSeats),
      baggage_info: JSON.stringify(baggageInfo),
      special_needs: JSON.stringify(specialNeedsData)
    };

    // Send AJAX request to backend for check-in processing
    fetch('aerocheck-kiosk.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams(checkInData)
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Check-in successful
        displayBoardingPass();
        goToPage(7);
      } else {
        // Check-in failed
        alert('Check-in failed: ' + (data.message || 'Unknown error'));
        confirmBtn.disabled = false;
        confirmBtn.textContent = "Confirm and Proceed";
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Network error, please try again');
      confirmBtn.disabled = false;
      confirmBtn.textContent = "Confirm and Proceed";
    });
  }

  function displayBoardingPass() {
    const boardingPassDiv = document.getElementById("boardingPassDetails");
    boardingPassDiv.innerHTML = "";

    selectedPassengers.forEach((passengerId) => {
      const passenger = passengersData.find(
        (p) => (p.id || p.passenger_id) === passengerId
      );
      const passDiv = document.createElement("div");
      passDiv.innerHTML = `
                <div class="boarding-pass-details">
                    <p><strong>Passenger:</strong> ${
                      passenger.firstName || passenger.first_name || ""
                    } ${passenger.lastName || passenger.last_name || ""}</p>
                    <p><strong>Flight:</strong> ${
                      flightData.flight_number ||
                      bookingData.flightNumber ||
                      "N/A"
                    }</p>
                    <p><strong>Seat:</strong> ${
                      passengerSeats[passengerId]
                    }</p>
                    <p><strong>Gate:</strong> ${
                      flightData.gate || bookingData.gate || "N/A"
                    }</p>
                    <p><strong>Departure:</strong> ${
                      flightData.departure_time ||
                      bookingData.departureTime ||
                      "N/A"
                    }</p>
                    <p><strong>Destination:</strong> ${
                      flightData.destination || bookingData.destination || "N/A"
                    }</p>
                </div>
            `;
      boardingPassDiv.appendChild(passDiv);
    });
  }

  function sendEBoardingPass() {
    const mobile = document.getElementById("mobileNumber").value;
    if (!mobile) {
      alert("Please enter mobile number");
      return;
    }

    const messageDiv = document.getElementById("sendMessage");
    messageDiv.className = "success-message";
    messageDiv.innerHTML = `
            Sent to your contact number: ${mobile}
        `;
    messageDiv.classList.remove("send-message");
  }

  function finishCheckIn() {
    goToPage(8);

    // Return to welcome page after 5 seconds
    setTimeout(() => {
      resetSystem();
      goToPage(1);
    }, 5000);
  }

  function resetSystem() {
    // Reset all variables
    currentPage = 1;
    activeInput = null;
    bookingData = null;
    selectedPassengers = [];
    currentSeatPassenger = 0;
    passengerSeats = {};
    baggageInfo = {};
    specialNeedsData = {};
    passengersData = [];
    baggageItems = []; // Reset baggage items
    baggagePackages = []; // Reset baggage packages
    baggagePaid = false; // Reset payment status
    amountPaid = 0; // Reset paid amount
    paidPackageId = null; // Reset paid package

    // Clear forms
    document.getElementById("bookingRef").value = "";
    document.getElementById("lastName").value = "";
    document.getElementById("mobileNumber").value = "";
    document.getElementById("specialNotes").value = "";

    // Clear special needs checkboxes
    const specialCheckboxes = document.querySelectorAll('.special-options input[type="checkbox"]');
    specialCheckboxes.forEach(cb => { cb.checked = false; });

    // Clear baggage related elements
    const baggageContainer = document.getElementById("baggageItemsContainer");
    if (baggageContainer) baggageContainer.innerHTML = "";

    const baggageCountDisplay = document.getElementById("baggageCountDisplay");
    if (baggageCountDisplay) baggageCountDisplay.textContent = "0";

    const baggageWeightDisplay = document.getElementById(
      "baggageWeightDisplay"
    );
    if (baggageWeightDisplay) baggageWeightDisplay.textContent = "0";

    const baggagePackageSelect = document.getElementById(
      "baggagePackageSelect"
    );
    if (baggagePackageSelect)
      baggagePackageSelect.innerHTML =
        '<option value="">Please select</option>';

    const baggagePackageDesc = document.getElementById("baggagePackageDesc");
    if (baggagePackageDesc) baggagePackageDesc.textContent = "";

    const baggagePackageError = document.getElementById("baggagePackageError");
    if (baggagePackageError)
      baggagePackageError.classList.add("baggage-package-error");

    // Hide messages
    const errorMessage = document.getElementById("errorMessage");
    if (errorMessage) errorMessage.classList.add("error-message-hidden");

    const sendMessage = document.getElementById("sendMessage");
    if (sendMessage) sendMessage.classList.add("send-message");

    const specialOptionsContainer = document.getElementById(
      "specialOptionsContainer"
    );
    if (specialOptionsContainer)
      specialOptionsContainer.classList.add("special-options-container");

    // Reset buttons
    const confirmBtn = document.querySelector(
      'button[onclick="confirmCheckIn()"]'
    );
    if (confirmBtn) {
      confirmBtn.disabled = false;
      confirmBtn.textContent = "Confirm and Proceed";
    }
  }

  // [MODIFIED] Rewrite goToPage function to include new page 4 initialization logic

  goToPage = function(pageNum) {
    // When returning from page 5 or page 3 to page 4, preserve data
    if(pageNum===4 && (currentPage===5 || currentPage===3)) {
        origGoToPage(pageNum);
        fetchBaggagePackages().then(()=>{
            renderBaggageItems();
            updatePayButtonState(
                parseFloat(document.getElementById('baggageWeightDisplay').textContent) || 0,
                parseInt(document.getElementById('baggageCountDisplay').textContent) || 0
            );
        });
        return;
    }
    // Other cases (first time entering page 4), initialize
    origGoToPage(pageNum);
    if(pageNum===4) {
        fetchBaggagePackages().then(()=>{
            baggageItems = [];
            baggagePaid = false;
            amountPaid = 0;
            paidPackageId = null;
            if(selectedPassengers.length > 0) {
                const ownerObj = passengersData.find(pp => String(pp.id || pp.passenger_id) === String(selectedPassengers[0])) || null;
                baggageItems.push({weight:'',owner:ownerObj,handling:'None'});
            }
            renderBaggageItems();
            updatePayButtonState(0, 0);
        });
    }
    // Reset all data when returning to home page
    if(pageNum===1) {
        baggageItems = [];
        baggagePaid = false;
        amountPaid = 0;
        paidPackageId = null;
    }
  };

  // New: Sync baggage information to baggageInfo
  function updateBaggageInfo() {
    baggageInfo.count = baggageItems.filter(item => parseFloat(item.weight) > 0).length;
    baggageInfo.totalWeight = baggageItems.reduce((sum, item) => sum + (parseFloat(item.weight) || 0), 0);
    // Add detailed baggage item information, ensure owner_id and special_handling
    baggageInfo.items = baggageItems.filter(item => parseFloat(item.weight) > 0).map(item => ({
      weight: parseFloat(item.weight) || 0,
      owner_id: item.owner ? (item.owner.passenger_id || item.owner.id) : '',
      handling: item.handling || 'None',
      special_handling: item.handling && item.handling !== 'None' ? item.handling : null
    }));
    const pkgSelect = document.getElementById('baggagePackageSelect');
    if (pkgSelect && pkgSelect.value) {
      const pkg = baggagePackages.find(p => p.package_id === pkgSelect.value);
      baggageInfo.packageName = pkg ? pkg.package_name : '';
      baggageInfo.packageId = pkgSelect.value;
    } else {
      baggageInfo.packageName = '';
      baggageInfo.packageId = '';
    }
  }

  // New: Prevent navigation when no package is selected and there's baggage
  function proceedToSpecialNeeds() {
    updateBaggageInfo(); // New: Sync baggage information
    const pkgSelect = document.getElementById('baggagePackageSelect');
    const totalWeight = parseFloat(document.getElementById('baggageWeightDisplay').textContent) || 0;
    const count = parseInt(document.getElementById('baggageCountDisplay').textContent) || 0;
    const errorDiv = document.getElementById('baggagePackageError');
    if (count > 0 && totalWeight > 0 && (!pkgSelect.value || pkgSelect.value === '')) {
      errorDiv.textContent = 'Please select a suitable baggage package';
      errorDiv.style.display = 'block';
      return;
    } else if (count > 0 && totalWeight > 0 && !baggagePaid) {
      errorDiv.textContent = 'Please complete payment first';
      errorDiv.style.display = 'block';
      return;
    } else {
      errorDiv.textContent = '';
      errorDiv.style.display = 'none';
    }
    goToPage(5);
  }

  function setBaggageWeightMax() {
    const pkgSelect = document.getElementById('baggagePackageSelect');
    let maxWeight = null;
    if (pkgSelect && pkgSelect.value) {
      const pkg = baggagePackages.find(p => p.package_id === pkgSelect.value);
      if (pkg) maxWeight = parseFloat(pkg.additional_weight_kg);
    }
    console.log('maxWeight =', maxWeight)
    document.querySelectorAll('.baggage-weight-input').forEach(input => {
      if (maxWeight) {
        input.max = maxWeight;
        // If current value exceeds maximum, auto-correct
        if (parseFloat(input.value) > maxWeight) input.value = maxWeight;
      } else {
        input.removeAttribute('max');
      }
    });
  }

  // Print baggage tags (simulation)
  function printBaggageTags() {
    alert('Baggage tags printed (simulation)');
  }
</script>
  </body>
</html>