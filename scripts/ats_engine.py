import sys
import json
import re
import os
import warnings
import hashlib

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
os.environ["TOKENIZERS_PARALLELISM"] = "false"
os.environ["TRANSFORMERS_VERBOSITY"] = "error"
warnings.filterwarnings("ignore")

import PyPDF2

nlp = None
try:
    import spacy
    nlp = spacy.load("en_core_web_sm")
except Exception:
    pass 

model = None
try:
    from sentence_transformers import SentenceTransformer, util
    model = SentenceTransformer('all-MiniLM-L6-v2')
except Exception:
    pass 

HEADERS_BLACKLIST = ['career objective', 'objective', 'education', 'experience', 'projects', 'skills', 'declaration', 'hobbies', 'personal details', 'summary', 'profile', 'contact']

def extract_insights(text, job_keywords):
    """Extracts both strengths (matched) and gaps (missing)."""
    keywords_list = [k.strip().title() for k in job_keywords.split(',') if k.strip()]
    text_lower = text.lower()
    
    strengths = []
    gaps = []
    
    for kw in keywords_list:
        if kw.lower() in text_lower:
            strengths.append(kw)
        else:
            gaps.append(kw)
            
    str_res = ", ".join(strengths) if strengths else "No specific matches"
    gap_res = ", ".join(gaps) if gaps else "No major gaps detected"
    
    return str_res, gap_res, len(strengths), len(keywords_list)

def extract_experience(text):
    exp_match = re.findall(r'\b(\d{1,2})\+?\s*(?:years|yrs?|yr)\b(?:\s*of\s*experience)?', text, re.IGNORECASE)
    if exp_match:
        valid_years = [int(y) for y in exp_match if int(y) < 40]
        if valid_years: return max(valid_years)
    return 0

def generate_local_summary(text, keywords_str):
    sentences = re.split(r'(?<=[.!?]) +', text)
    if not sentences: return "No summary available."
    keywords = [k.strip().lower() for k in keywords_str.split(',') if k.strip()]
    scored_sentences = []
    for sentence in sentences:
        if len(sentence.split()) < 5 or len(sentence.split()) > 40: continue 
        score = sum(1 for kw in keywords if kw in sentence.lower())
        scored_sentences.append((score, sentence.strip().replace('\n', ' ')))
        
    scored_sentences.sort(key=lambda x: x[0], reverse=True)
    top_sentences = [s[1] for s in scored_sentences[:2] if s[0] > 0]
    
    if top_sentences: return " ".join(top_sentences)
    else: return " ".join([s for s in sentences[:3] if len(s.split()) > 5]).replace('\n', ' ')

def extract_contact_info(text):
    phone_match = re.search(r'(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4,5}', text)
    email_match = re.search(r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+', text)
    email = email_match.group(0) if email_match else "N/A"
    phone = phone_match.group(0) if phone_match else "N/A"
    name = None
    lines = [line.strip() for line in text.split('\n') if line.strip() and len(line) < 60]
    
    for line in lines[:8]:
        if any(header in line.lower() for header in HEADERS_BLACKLIST) or any(char.isdigit() for char in line) or '@' in line: continue
        words = line.split()
        if 2 <= len(words) <= 4 and line[0].isupper():
            name = line.title()
            break
            
    if not name and nlp:
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
                if extracted: text += extracted + " "
                    
        if not text.strip():
            print(json.dumps({"status": "error", "message": "Empty PDF."}))
            return

        file_hash = hashlib.md5(text.encode('utf-8')).hexdigest()
        name, email, phone = extract_contact_info(text)
        strengths, gaps, match_count, total_kw = extract_insights(text, job_requirements)
        experience = extract_experience(text)
        summary = generate_local_summary(text, job_requirements)

        # Scoring
        keyword_score = (match_count / total_kw) * 100 if total_kw > 0 else 0
        final_score = 0
        
        if model and job_requirements.strip():
            resume_emb = model.encode(text, convert_to_tensor=True)
            req_emb = model.encode(job_requirements, convert_to_tensor=True)
            cosine_scores = util.cos_sim(resume_emb, req_emb)
            semantic_score = float(cosine_scores[0][0].item()) * 100
            final_score = int((semantic_score * 0.7) + (keyword_score * 0.3))
        else:
            final_score = int(keyword_score)

        final_score = max(0, min(100, final_score))
        
        # Recommendations based on score
        if final_score >= 80 and experience >= 2: rec = "Highly Recommended"
        elif final_score >= 60: rec = "Recommended"
        elif final_score >= 40: rec = "Review Needed"
        else: rec = "Not Recommended"
        
        if name == "Unknown Candidate":
            name = filepath.split('/')[-1].split('\\')[-1].split('_', 1)[-1].replace('.pdf', '').replace('-', ' ').title()

        result = {
            "status": "success",
            "name": name.strip()[:50], 
            "email": email,
            "phone": phone,
            "skills": strengths, # Saved as strengths
            "gaps": gaps,        # New gaps metric
            "experience_years": experience,
            "match_score": final_score,
            "resume_summary": summary,
            "recommendation": rec,
            "file_hash": file_hash
        }
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) < 3: sys.exit(1)
    analyze_resume(sys.argv[1], sys.argv[2])