Feature: Securitate Digitală
  Visitors can find practical security guidance and contact the editorial team.

  Scenario: Homepage presents recent guidance
    When I open the homepage
    Then the response status should be 200
    And the page should contain "Ultimele articole"
    And the page should contain "10 reguli simple ca să îți protejezi datele personale online"

  Scenario: Search finds a phishing guide
    When I search articles for "phishing"
    Then the response status should be 200
    And the page should contain "Cum recunoști un e-mail de tip phishing"

  Scenario: Category filter limits results
    When I filter articles by category "fraude-online"
    Then the response status should be 200
    And the page should contain "Cum recunoști un e-mail de tip phishing"
    And the page should not contain "10 reguli simple ca să îți protejezi datele personale online"

  Scenario: Old category links redirect to the renamed section
    When I filter articles by category "phishing"
    Then the response status should be 301
    And the response should redirect to "/articole?categorie=fraude-online"

  Scenario: Unknown article returns not found
    When I open article "articol-inexistent"
    Then the response status should be 404

  Scenario: Admin area requires authentication
    When I open the admin dashboard
    Then the response should redirect to the admin login page

  Scenario: Visitor sends a contact message with consent
    When I submit a valid contact message with privacy consent
    Then the response should redirect
    And the success message should be shown
    And the contact message should be stored

  Scenario: Contact form requires privacy consent
    When I submit a valid contact message without privacy consent
    Then the response should contain a privacy consent validation error
    And no contact message should be stored
