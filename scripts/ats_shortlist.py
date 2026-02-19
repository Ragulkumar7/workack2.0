import sys
import json
import re
import os
import warnings

# 1. Force UTF-8 output
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

# 2. Suppress warnings
os.environ["TOKENIZERS_PARALLELISM"] = "false"
warnings.filterwarnings("ignore")

import PyPDF2

try:
    from sentence_transformers import SentenceTransformer, util
    import spacy
    nlp = spacy.load("en_core_web_sm")
    model = SentenceTransformer('all-MiniLM-L6-v2')
except Exception as e:
    print(json.dumps({"status": "error", "message": f"AI Load Error: {str(e)}"}))
    sys.exit(1)

# Common headers to ignore so we don't extract "Career Objective" as a name
HEADERS_BLACKLIST = [
    'career objective', 'objective', 'education', 'experience', 'projects', 
    'skills', 'technical skills', 'declaration', 'hobbies', 'interests', 
    'personal details', 'summary', 'profile', 'contact', 'curriculum vitae', 'resume'
]

def extract_skills(text, job_keywords):
    """Matches user keywords against resume text."""
    keywords_list = [k.strip().lower() for k in job_keywords.split(',') if k.strip()]
    text_lower = text.lower()
    
    matched_skills = []
    for kw in keywords_list:
        if kw in text_lower:
            matched_skills.append(kw.strip().title()) # Return Title Cased (e.g. Python)
            
    return ", ".join(matched_skills) if matched_skills else "No specific matches"

def extract_contact_info(text):
    phone_match = re.search(r'(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4,5}', text)
    email_match = re.search(r'[\w\.-]+@[\w\.-]+\.\w+', text)
    
    email = email_match.group(0) if email_match else "N/A"
    phone = phone_match.group(0) if phone_match else "N/A"
    
    name = None
    lines = [line.strip() for line in text.split('\n') if line.strip() and len(line) < 60]
    
    # Strategy 1: Look for capitalized lines that are NOT headers
    for line in lines[:8]: # Check first 8 lines
        # Skip if line contains blacklisted headers
        if any(header in line.lower() for header in HEADERS_BLACKLIST):
            continue
            
        # Skip if line contains numbers or emails
        if any(char.isdigit() for char in line) or '@' in line:
            continue
            
        # Check if it looks like a Name (Title Case, 2-4 words)
        words = line.split()
        if 2 <= len(words) <= 4 and line[0].isupper():
            name = line.title()
            break
            
    # Strategy 2: Fallback to Spacy NER
    if not name:
        doc = nlp(text[:2000])
        for ent in doc.ents:
            if ent.label_ == "PERSON":
                clean_ent = ent.text.replace('\n', ' ').strip()
                if not any(header in clean_ent.lower() for header in HEADERS_BLACKLIST):
                    name = clean_ent.title()
                    break
                    
    return name if name else "Unknown Candidate", email, phone

def analyze_resume(filepath, job_requirements):
    try:
        text = ""
        with open(filepath, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page in reader.pages:
                extracted = page.extract_text()
                if extracted:
                    text += extracted + " "
                    
        if not text.strip():
            print(json.dumps({"status": "error", "message": "Could not extract text from this PDF."}))
            return

        name, email, phone = extract_contact_info(text)
        skills = extract_skills(text, job_requirements)
        text_lower = text.lower()

        # --- SCORING LOGIC ---
        keywords_list = [k.strip().lower() for k in job_requirements.split(',') if k.strip()]
        matches = 0
        for kw in keywords_list:
            if kw in text_lower:
                matches += 1
        
        # Keyword Score (30%)
        keyword_score = 0
        if len(keywords_list) > 0:
            keyword_score = (matches / len(keywords_list)) * 100
        
        # Semantic Score (70%)
        resume_embedding = model.encode(text, convert_to_tensor=True)
        requirements_embedding = model.encode(job_requirements, convert_to_tensor=True)
        cosine_scores = util.cos_sim(resume_embedding, requirements_embedding)
        semantic_score = float(cosine_scores[0][0]) * 100
        
        # Combine
        final_score = (semantic_score * 0.7) + (keyword_score * 0.3)
        final_score = int(max(0, min(100, final_score)))
        
        result = {
            "status": "success",
            "name": name, 
            "email": email,
            "phone": phone,
            "skills": skills,
            "match_score": final_score
        }
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"status": "error", "message": "Missing arguments."}))
        sys.exit(1)
    file_path = sys.argv[1]
    job_reqs = sys.argv[2]
    analyze_resume(file_path, job_reqs)